<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Src\Modules\Billing\Application\DTO\CreateWalletTopupInput;
use Src\Modules\Billing\Application\DTO\PayInvoiceInput;
use Src\Modules\Billing\Application\DTO\SubscribeToPlanInput;
use Src\Modules\Billing\Application\UseCase\CancelSubscription;
use Src\Modules\Billing\Application\UseCase\CreateWalletTopup;
use Src\Modules\Billing\Application\UseCase\PayInvoice;
use Src\Modules\Billing\Application\UseCase\SubscribeToPlan;
use Src\Modules\Billing\Application\UseCase\SyncPaymentStatus;
use Src\Modules\Billing\Domain\Entity\Payment;
use Src\Modules\Billing\Domain\Exception\InvoiceNotFound;
use Src\Modules\Billing\Domain\Exception\PaymentGatewayError;
use Src\Modules\Billing\Domain\Exception\PaymentNotFound;
use Src\Modules\Billing\Domain\Exception\SubscriptionNotFound;
use Src\Modules\Billing\Domain\Repository\PlanRepository;
use Src\Modules\Billing\Domain\Repository\SubscriptionRepository;
use Src\Modules\Billing\Domain\ValueObject\PaymentMethod;
use Src\Modules\Billing\Domain\ValueObject\PlanStatus;
use Src\Modules\Identity\Domain\ValueObject\Role;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;
use Src\Shared\Application\Contracts\TransactionManager;

/**
 * Cobranças PIX geradas pelo admin em nome do cliente: assinar um plano,
 * recarga avulsa ou pagar uma fatura em aberto. O admin repassa o QR /
 * copia-e-cola ao cliente; o saldo só entra após a confirmação do pagamento
 * (webhook ou polling), pelo mesmo caminho do portal do cliente.
 */
final class AccountChargesAdminController
{
    /** Atribui o plano (assinatura manual) e gera o PIX da primeira fatura. */
    public function plan(
        string $accountId,
        Request $request,
        SubscribeToPlan $subscribe,
        SubscriptionRepository $subscriptions,
        PlanRepository $plans,
        TransactionManager $tx,
    ): RedirectResponse {
        AccountModel::query()->findOrFail($accountId);

        $data = $request->validate([
            'plan_id' => [
                'required',
                'string',
                Rule::exists('plans', 'id')->where('status', PlanStatus::Active->value),
            ],
            'payer_email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        // Duas assinaturas ativas gerariam duas faturas de renovação por ciclo.
        if ($subscriptions->findActiveByAccount($accountId) !== null) {
            throw ValidationException::withMessages([
                'plan_id' => 'A conta já possui uma assinatura ativa. Cancele-a antes de atribuir outro plano.',
            ]);
        }

        if (($plans->findById($data['plan_id'])?->price->cents ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'plan_id' => 'Plano sem valor a cobrar. Use a atribuição por cortesia.',
            ]);
        }

        $payerEmail = $this->payerEmail($accountId, $data['payer_email'] ?? null);

        try {
            // Atômico: se o gateway recusar o PIX, não sobra assinatura/fatura órfã.
            $result = $tx->transactional(fn () => $subscribe->handle(new SubscribeToPlanInput(
                accountId: $accountId,
                planId: $data['plan_id'],
                method: PaymentMethod::Pix,
                payerEmail: $payerEmail,
                backUrl: route('client.billing.index'),
            )));
        } catch (PaymentGatewayError $e) {
            return $this->gatewayFailure($e);
        }

        $response = back()->with('success', 'Plano atribuído. Envie o PIX ao cliente — o saldo entra após o pagamento.');

        if (isset($result['payment']) && $result['payment'] instanceof Payment) {
            $response->with('payment', $this->serializePayment($result['payment']));
        }

        return $response;
    }

    /** Recarga avulsa de saldo via PIX. */
    public function topup(string $accountId, Request $request, CreateWalletTopup $topup): RedirectResponse
    {
        AccountModel::query()->findOrFail($accountId);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'payer_email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        try {
            $payment = $topup->handle(new CreateWalletTopupInput(
                accountId: $accountId,
                amountCents: (int) round(((float) $data['amount']) * 100),
                method: PaymentMethod::Pix,
                payerEmail: $this->payerEmail($accountId, $data['payer_email'] ?? null),
            ));
        } catch (PaymentGatewayError $e) {
            return $this->gatewayFailure($e);
        }

        return back()
            ->with('payment', $this->serializePayment($payment))
            ->with('success', 'PIX de recarga gerado.');
    }

    /** PIX para uma fatura em aberto/vencida da conta (ex.: renovação do plano). */
    public function invoice(string $accountId, string $invoiceId, Request $request, PayInvoice $payInvoice): RedirectResponse
    {
        AccountModel::query()->findOrFail($accountId);

        $data = $request->validate([
            'payer_email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        try {
            $payment = $payInvoice->handle(new PayInvoiceInput(
                accountId: $accountId,
                invoiceId: $invoiceId,
                method: PaymentMethod::Pix,
                payerEmail: $this->payerEmail($accountId, $data['payer_email'] ?? null),
            ));
        } catch (InvoiceNotFound) {
            return back()->with('error', 'Fatura não encontrada ou já não está em aberto.');
        } catch (PaymentGatewayError $e) {
            return $this->gatewayFailure($e);
        }

        return back()
            ->with('payment', $this->serializePayment($payment))
            ->with('success', 'PIX da fatura gerado.');
    }

    /** Polling do painel: reconsulta o pagamento no gateway e liquida se aprovado. */
    public function status(string $accountId, string $paymentId, SyncPaymentStatus $sync): JsonResponse
    {
        try {
            $payment = $sync->handle($paymentId, $accountId);
        } catch (PaymentNotFound) {
            abort(404);
        }

        return response()->json([
            'id' => $payment->id,
            'status' => $payment->status->value,
        ]);
    }

    public function cancelSubscription(string $accountId, string $subscriptionId, CancelSubscription $cancel): RedirectResponse
    {
        try {
            $cancel->handle($subscriptionId, $accountId);
        } catch (SubscriptionNotFound) {
            abort(404);
        }

        return back()->with('success', 'Assinatura cancelada.');
    }

    /**
     * Email do pagador exigido pelo gateway: o informado pelo admin ou, na
     * falta, o do primeiro usuário (cliente) vinculado à conta.
     */
    private function payerEmail(string $accountId, ?string $informed): string
    {
        $email = $informed ?: User::query()
            ->where('account_id', $accountId)
            ->where('role', Role::Client->value)
            ->orderBy('id')
            ->value('email');

        if (! is_string($email) || $email === '') {
            throw ValidationException::withMessages([
                'payer_email' => 'Informe o email do pagador: a conta não tem usuário vinculado.',
            ]);
        }

        return $email;
    }

    private function gatewayFailure(PaymentGatewayError $e): RedirectResponse
    {
        report($e);

        return back()->with('error', 'Não foi possível gerar o PIX no gateway de pagamento. '.$e->getMessage());
    }

    /** @return array<string, mixed> */
    private function serializePayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'type' => $payment->type->value,
            'method' => $payment->method->value,
            'status' => $payment->status->value,
            'amount_cents' => $payment->amountCents,
            'qr_code' => $payment->qrCode,
            'qr_code_base64' => $payment->qrCodeBase64,
            'ticket_url' => $payment->ticketUrl,
            'barcode' => $payment->barcode,
            'expires_at' => $payment->expiresAt?->format('c'),
        ];
    }
}

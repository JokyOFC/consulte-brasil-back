<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Http\Controllers\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Billing\Application\DTO\CreateWalletTopupInput;
use Src\Modules\Billing\Application\DTO\PayInvoiceInput;
use Src\Modules\Billing\Application\DTO\SubscribeToPlanInput;
use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Application\UseCase\CancelInvoice;
use Src\Modules\Billing\Application\UseCase\CancelSubscription;
use Src\Modules\Billing\Application\UseCase\ChangeSubscription;
use Src\Modules\Billing\Application\UseCase\CreateWalletTopup;
use Src\Modules\Billing\Application\UseCase\PayInvoice;
use Src\Modules\Billing\Application\UseCase\SubscribeToPlan;
use Src\Modules\Billing\Application\UseCase\SyncPaymentStatus;
use Src\Modules\Billing\Domain\Entity\Payment;
use Src\Modules\Billing\Domain\Exception\InvoiceNotCancelable;
use Src\Modules\Billing\Domain\Exception\PaymentNotFound;
use Src\Modules\Billing\Domain\Repository\PlanRepository;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\CreditTransactionModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\InvoiceModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PaymentModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\SubscriptionModel;
use Src\Modules\Billing\Domain\ValueObject\PaymentMethod;

/**
 * Portal financeiro do cliente: carteira, recarga (PIX/boleto/cartão),
 * faturas (em aberto/vencidas/próxima), histórico e gestão da assinatura.
 */
final class ClientBillingController
{
    public function index(Request $request, WalletRepository $wallets, PlanRepository $plans, PaymentGateway $gateway, CancelInvoice $cancelInvoice): Response
    {
        $accountId = $request->user()->account_id;
        $wallet = $accountId !== null ? $wallets->findByAccountId($accountId) : null;

        $invoices = $accountId === null ? [] : InvoiceModel::query()
            ->where('account_id', $accountId)
            ->whereIn('status', ['open', 'overdue'])
            ->orderBy('due_date')
            ->get()
            ->map(function (InvoiceModel $i) use ($accountId, $cancelInvoice) {
                $cancelableAt = $cancelInvoice->cancelableAtForInvoice($i->id, $accountId);

                return [
                    'id' => $i->id,
                    'number' => $i->number,
                    'status' => $i->status,
                    'amount_cents' => (int) $i->amount_cents,
                    'description' => $i->description,
                    'due_date' => optional($i->due_date)->toDateString(),
                    'cancelable_at' => $cancelableAt?->format('c'),
                ];
            })->all();

        $subscriptionModel = $accountId === null ? null : SubscriptionModel::query()
            ->where('account_id', $accountId)
            ->whereIn('status', ['active', 'past_due'])
            ->orderByDesc('created_at')
            ->first();

        $subscription = $subscriptionModel === null ? null : [
            'id' => $subscriptionModel->id,
            'plan_id' => $subscriptionModel->plan_id,
            'plan_name' => PlanModel::query()->where('id', $subscriptionModel->plan_id)->value('name'),
            'status' => $subscriptionModel->status,
            'payment_method' => $subscriptionModel->payment_method,
            'next_billing_at' => optional($subscriptionModel->next_billing_at)->toDateString(),
        ];

        $transactions = $accountId === null ? [] : CreditTransactionModel::query()
            ->where('account_id', $accountId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['type', 'direction', 'amount', 'created_at'])
            ->map(fn ($t) => [
                'type' => $t->type,
                'direction' => $t->direction,
                'amount_cents' => (int) $t->amount,
                'created_at' => optional($t->created_at)->toDateTimeString(),
            ])->all();

        $payments = $accountId === null ? [] : PaymentModel::query()
            ->where('account_id', $accountId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (PaymentModel $p) => [
                'id' => $p->id,
                'type' => $p->type,
                'method' => $p->method,
                'status' => $p->status,
                'amount_cents' => (int) $p->amount_cents,
                'created_at' => optional($p->created_at)->toDateTimeString(),
            ])->all();

        return Inertia::render('client/billing/index', [
            'wallet' => $wallet === null ? null : [
                'balance' => $wallet->balance()->value,
                'reserved' => $wallet->reserved()->value,
                'available' => $wallet->available()->value,
            ],
            'invoices' => $invoices,
            'subscription' => $subscription,
            'transactions' => $transactions,
            'payments' => $payments,
            'plans' => array_map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price_cents' => $p->price->cents,
                'recharge_cents' => $p->includedCredits,
            ], $plans->active()),
            'mp_public_key' => $gateway->publicKey(),
        ]);
    }

    public function topup(Request $request, CreateWalletTopup $topup): RedirectResponse
    {
        $accountId = $this->requireAccountId($request);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', 'in:pix,boleto,credit_card'],
            'card_token' => ['required_if:method,credit_card', 'nullable', 'string'],
            'installments' => ['nullable', 'integer', 'min:1'],
            'payment_method_id' => ['required_if:method,credit_card', 'nullable', 'string'],
            'issuer_id' => ['nullable', 'string'],
        ]);

        $payment = $topup->handle(new CreateWalletTopupInput(
            accountId: $accountId,
            amountCents: (int) round(((float) $data['amount']) * 100),
            method: PaymentMethod::from($data['method']),
            payerEmail: (string) $request->user()->email,
            cardToken: $data['card_token'] ?? null,
            installments: (int) ($data['installments'] ?? 1),
            paymentMethodId: $data['payment_method_id'] ?? null,
            issuerId: $data['issuer_id'] ?? null,
        ));

        return back()->with('payment', $this->serializePayment($payment))
            ->with('success', 'Cobrança gerada.');
    }

    public function payInvoice(Request $request, PayInvoice $payInvoice): RedirectResponse
    {
        $accountId = $this->requireAccountId($request);

        $data = $request->validate([
            'invoice_id' => ['required', 'string'],
            'method' => ['required', 'in:pix,boleto,credit_card'],
            'card_token' => ['required_if:method,credit_card', 'nullable', 'string'],
            'installments' => ['nullable', 'integer', 'min:1'],
            'payment_method_id' => ['required_if:method,credit_card', 'nullable', 'string'],
            'issuer_id' => ['nullable', 'string'],
        ]);

        $payment = $payInvoice->handle(new PayInvoiceInput(
            accountId: $accountId,
            invoiceId: $data['invoice_id'],
            method: PaymentMethod::from($data['method']),
            payerEmail: (string) $request->user()->email,
            cardToken: $data['card_token'] ?? null,
            installments: (int) ($data['installments'] ?? 1),
            paymentMethodId: $data['payment_method_id'] ?? null,
            issuerId: $data['issuer_id'] ?? null,
        ));

        return back()->with('payment', $this->serializePayment($payment))
            ->with('success', 'Cobrança da fatura gerada.');
    }

    public function subscribe(Request $request, SubscribeToPlan $subscribe): RedirectResponse
    {
        $accountId = $this->requireAccountId($request);

        $data = $request->validate([
            'plan_id' => ['required', 'string'],
            'method' => ['required', 'in:pix,boleto,credit_card'],
            'card_token' => ['required_if:method,credit_card', 'nullable', 'string'],
            'installments' => ['nullable', 'integer', 'min:1'],
            'payment_method_id' => ['required_if:method,credit_card', 'nullable', 'string'],
            'issuer_id' => ['nullable', 'string'],
        ]);

        $result = $subscribe->handle(new SubscribeToPlanInput(
            accountId: $accountId,
            planId: $data['plan_id'],
            method: PaymentMethod::from($data['method']),
            payerEmail: (string) $request->user()->email,
            backUrl: route('client.billing.index'),
            cardToken: $data['card_token'] ?? null,
            installments: (int) ($data['installments'] ?? 1),
            paymentMethodId: $data['payment_method_id'] ?? null,
            issuerId: $data['issuer_id'] ?? null,
        ));

        $response = back()->with('success', 'Assinatura criada.');

        if (isset($result['payment']) && $result['payment'] instanceof Payment) {
            $response->with('payment', $this->serializePayment($result['payment']));
        }

        return $response;
    }

    public function cancelSubscription(string $subscriptionId, Request $request, CancelSubscription $cancel): RedirectResponse
    {
        $cancel->handle($subscriptionId, $this->requireAccountId($request));

        return back()->with('success', 'Assinatura cancelada.');
    }

    public function changeSubscription(string $subscriptionId, Request $request, ChangeSubscription $change): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'string']]);
        $change->handle($subscriptionId, $this->requireAccountId($request), $data['plan_id']);

        return back()->with('success', 'Plano alterado.');
    }

    public function cancelInvoice(string $invoiceId, Request $request, CancelInvoice $cancel): RedirectResponse
    {
        try {
            $cancel->handle($invoiceId, $this->requireAccountId($request));
        } catch (InvoiceNotCancelable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Fatura cancelada.');
    }

    public function paymentStatus(string $paymentId, Request $request, SyncPaymentStatus $sync): JsonResponse
    {
        try {
            $payment = $sync->handle($paymentId, $request->user()->account_id);
        } catch (PaymentNotFound) {
            abort(404);
        }

        return response()->json([
            'id' => $payment->id,
            'status' => $payment->status->value,
        ]);
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

    private function requireAccountId(Request $request): string
    {
        $accountId = $request->user()->account_id;
        abort_if($accountId === null, 403, 'Conta não associada ao usuário.');

        return $accountId;
    }
}

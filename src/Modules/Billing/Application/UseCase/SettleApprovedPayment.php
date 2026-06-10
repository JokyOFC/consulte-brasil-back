<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use DateTimeImmutable;
use Src\Modules\Billing\Application\DTO\GrantCreditsInput;
use Src\Modules\Billing\Domain\Entity\Payment;
use Src\Modules\Billing\Domain\Repository\InvoiceRepository;
use Src\Modules\Billing\Domain\Repository\PaymentRepository;
use Src\Modules\Billing\Domain\Repository\PlanRepository;
use Src\Modules\Billing\Domain\Repository\SubscriptionRepository;
use Src\Modules\Billing\Domain\ValueObject\PaymentType;
use Src\Shared\Application\Contracts\Clock;
use Src\Shared\Application\Contracts\TransactionManager;

/**
 * Liquida um pagamento aprovado, de forma IDEMPOTENTE:
 *
 *  - Topup  → deposita o valor pago na carteira (GrantCredits).
 *  - Invoice → marca a fatura paga, deposita a recarga do plano e avança o
 *    ciclo da assinatura.
 *
 * A idempotência é garantida pela idempotencyKey "settle:{payment.id}" do
 * GrantCredits: reprocessar o mesmo pagamento (ex.: webhook + retorno do
 * cartão) NÃO credita duas vezes.
 */
final readonly class SettleApprovedPayment
{
    public function __construct(
        private PaymentRepository $payments,
        private InvoiceRepository $invoices,
        private SubscriptionRepository $subscriptions,
        private PlanRepository $plans,
        private GrantCredits $grantCredits,
        private TransactionManager $tx,
        private Clock $clock,
    ) {}

    public function handle(Payment $payment): void
    {
        $this->tx->transactional(function () use ($payment): void {
            $payment->markApproved($this->clock->now());
            $this->payments->save($payment);

            if ($payment->type === PaymentType::Topup) {
                $this->depositTopup($payment);

                return;
            }

            $this->settleInvoice($payment);
        });
    }

    private function depositTopup(Payment $payment): void
    {
        $this->grantCredits->handle(new GrantCreditsInput(
            accountId: $payment->accountId,
            amount: $payment->amountCents,
            referenceType: 'topup',
            referenceId: $payment->id,
            idempotencyKey: "settle:{$payment->id}",
            metadata: ['payment_id' => $payment->id, 'method' => $payment->method->value],
        ));
    }

    private function settleInvoice(Payment $payment): void
    {
        if ($payment->invoiceId === null) {
            // Sem fatura vinculada: trata como recarga avulsa do valor pago.
            $this->depositTopup($payment);

            return;
        }

        $invoice = $this->invoices->findById($payment->invoiceId);
        if ($invoice === null) {
            return;
        }

        if ($invoice->isPayable()) {
            $invoice->markPaid($payment->id, $this->clock->now());
            $this->invoices->save($invoice);
        }

        // Recarga do plano (valor creditado na carteira a cada ciclo).
        $rechargeCents = $invoice->amountCents;
        $subscription = $invoice->subscriptionId !== null
            ? $this->subscriptions->findById($invoice->subscriptionId)
            : null;

        if ($subscription !== null) {
            $plan = $this->plans->findById($subscription->planId);
            if ($plan !== null && $plan->includedCredits > 0) {
                $rechargeCents = $plan->includedCredits;
            }
        }

        $this->grantCredits->handle(new GrantCreditsInput(
            accountId: $payment->accountId,
            amount: $rechargeCents,
            referenceType: 'invoice',
            referenceId: $invoice->id,
            idempotencyKey: "settle:{$payment->id}",
            metadata: ['payment_id' => $payment->id, 'invoice_id' => $invoice->id],
        ));

        if ($subscription !== null) {
            $now = $this->clock->now();
            $next = $now->modify('+30 days');
            $subscription->currentPeriodStart = $now;
            $subscription->currentPeriodEnd = $next;
            $subscription->renewsAt = $next;
            $subscription->nextBillingAt = $next;
            $subscription->reactivate();
            $this->subscriptions->save($subscription);
        }
    }
}

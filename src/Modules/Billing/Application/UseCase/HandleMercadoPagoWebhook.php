<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Psr\Log\LoggerInterface;
use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Application\Service\InvoiceNumberGenerator;
use Src\Modules\Billing\Domain\Exception\PaymentGatewayError;
use Src\Modules\Billing\Domain\Entity\Invoice;
use Src\Modules\Billing\Domain\Entity\InvoiceItem;
use Src\Modules\Billing\Domain\Entity\Payment;
use Src\Modules\Billing\Domain\Repository\InvoiceRepository;
use Src\Modules\Billing\Domain\Repository\PaymentRepository;
use Src\Modules\Billing\Domain\Repository\PlanRepository;
use Src\Modules\Billing\Domain\Repository\SubscriptionRepository;
use Src\Modules\Billing\Domain\ValueObject\InvoiceStatus;
use Src\Modules\Billing\Domain\ValueObject\PaymentMethod;
use Src\Modules\Billing\Domain\ValueObject\PaymentStatus;
use Src\Modules\Billing\Domain\ValueObject\PaymentType;
use Src\Shared\Application\Contracts\Clock;
use Src\Shared\Application\Contracts\IdGenerator;

/**
 * Processa as notificações (webhook) do Mercado Pago. O pagamento é sempre
 * reconsultado no MP (fonte da verdade) e o crédito é IDEMPOTENTE:
 *
 *  - Pagamento local conhecido (recarga/fatura) → liquida.
 *  - Pagamento de Preapproval (recorrência) sem registro local → cria a
 *    fatura/pagamento do ciclo e credita a recarga do plano.
 *
 * A unicidade de payments.mp_payment_id garante que o mesmo pagamento nunca
 * gere dois lançamentos.
 */
final readonly class HandleMercadoPagoWebhook
{
    public function __construct(
        private PaymentGateway $gateway,
        private PaymentRepository $payments,
        private SubscriptionRepository $subscriptions,
        private PlanRepository $plans,
        private InvoiceRepository $invoices,
        private SettleApprovedPayment $settle,
        private InvoiceNumberGenerator $invoiceNumbers,
        private IdGenerator $ids,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    public function handle(string $type, string $dataId): void
    {
        if (! in_array($type, ['payment', 'payment.created', 'payment.updated'], true)) {
            // Outras notificações (merchant_order, preapproval) não creditam.
            $this->logger->info('mp.webhook.ignored', ['type' => $type, 'data_id' => $dataId]);

            return;
        }

        if ($this->isTestNotification($dataId)) {
            $this->logger->info('mp.webhook.test_notification', ['data_id' => $dataId]);

            return;
        }

        try {
            $status = $this->gateway->getPayment($dataId);
        } catch (PaymentGatewayError $e) {
            // Falha ao reconsultar no MP (ex.: ping de teste com ID fictício).
            // Retornamos 200 para o MP não ficar reenviando indefinidamente.
            $this->logger->warning('mp.webhook.payment_fetch_failed', [
                'data_id' => $dataId,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        $mpStatus = PaymentStatus::fromMercadoPago($status->status);

        $existing = $this->payments->findByMpPaymentId($dataId);

        if ($existing !== null) {
            $existing->updateStatus($mpStatus);
            $this->payments->save($existing);

            if ($mpStatus === PaymentStatus::Approved) {
                $this->settle->handle($existing);
            }

            return;
        }

        // Pagamento desconhecido localmente: pode ser cobrança recorrente do
        // Preapproval (external_reference = "subscription:{id}").
        $reference = (string) $status->externalReference;

        if (! str_starts_with($reference, 'subscription:')) {
            $this->logger->info('mp.webhook.unmatched_payment', [
                'data_id' => $dataId,
                'external_reference' => $reference,
            ]);

            return;
        }

        $this->handleRecurringCharge(substr($reference, strlen('subscription:')), $dataId, $status->amountCents, $mpStatus);
    }

    private function handleRecurringCharge(string $subscriptionId, string $mpPaymentId, int $amountCents, PaymentStatus $mpStatus): void
    {
        $subscription = $this->subscriptions->findById($subscriptionId);
        if ($subscription === null) {
            return;
        }

        $plan = $this->plans->findById($subscription->planId);
        $now = $this->clock->now();
        $next = $now->modify('+30 days');
        $invoiceAmount = $plan?->price->cents ?? $amountCents;

        $invoice = new Invoice(
            id: $this->ids->generate(),
            accountId: $subscription->accountId,
            subscriptionId: $subscription->id,
            status: InvoiceStatus::Open,
            amountCents: $invoiceAmount,
            description: $plan !== null ? "Plano {$plan->name}" : 'Assinatura',
            dueDate: $now,
            periodStart: $now,
            periodEnd: $next,
            items: [new InvoiceItem($this->ids->generate(), $plan !== null ? "Plano {$plan->name}" : 'Assinatura', $invoiceAmount)],
            metadata: ['origin' => 'preapproval'],
            createdAt: $now,
            number: $this->invoiceNumbers->next(),
        );
        $this->invoices->save($invoice);

        $payment = new Payment(
            id: $this->ids->generate(),
            accountId: $subscription->accountId,
            type: PaymentType::Invoice,
            method: PaymentMethod::CreditCard,
            status: $mpStatus,
            amountCents: $amountCents,
            invoiceId: $invoice->id,
            mpPaymentId: $mpPaymentId,
            mpPreapprovalId: $subscription->mpPreapprovalId,
            description: $invoice->description,
            createdAt: $now,
        );
        $this->payments->save($payment);

        if ($mpStatus === PaymentStatus::Approved) {
            $this->settle->handle($payment);
        }
    }

    /** IDs usados pelo painel do Mercado Pago ao testar a URL do webhook. */
    private function isTestNotification(string $dataId): bool
    {
        return in_array($dataId, ['123456', '123456789'], true);
    }
}

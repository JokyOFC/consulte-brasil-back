<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use DateTimeImmutable;
use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Domain\Entity\Payment;
use Src\Modules\Billing\Domain\Exception\InvoiceNotCancelable;
use Src\Modules\Billing\Domain\Exception\InvoiceNotFound;
use Src\Modules\Billing\Domain\Exception\PaymentGatewayError;
use Src\Modules\Billing\Domain\Repository\InvoiceRepository;
use Src\Modules\Billing\Domain\Repository\PaymentRepository;
use Src\Modules\Billing\Domain\ValueObject\PaymentStatus;
use Src\Shared\Application\Contracts\Clock;

/**
 * Cancela uma fatura em aberto. Se houver cobrança PIX/boleto pendente,
 * aguarda um intervalo mínimo antes de permitir o cancelamento.
 */
final readonly class CancelInvoice
{
    /** Tempo mínimo após gerar a cobrança antes de cancelar a fatura. */
    public const CANCEL_GRACE_SECONDS = 120;

    public function __construct(
        private InvoiceRepository $invoices,
        private PaymentRepository $payments,
        private PaymentGateway $gateway,
        private SyncPaymentStatus $syncPayment,
        private Clock $clock,
    ) {}

    public function handle(string $invoiceId, string $accountId): void
    {
        $invoice = $this->invoices->findById($invoiceId);

        if ($invoice === null || ! $invoice->isPayable() || $invoice->accountId !== $accountId) {
            throw InvoiceNotFound::withId($invoiceId);
        }

        $pending = $this->payments->findLatestPendingByInvoiceId($invoiceId);

        if ($pending !== null) {
            $pending = $this->syncPayment->sync($pending);

            if ($pending->status === PaymentStatus::Approved) {
                throw InvoiceNotCancelable::alreadyPaid();
            }

            if (! $pending->status->isFinal()) {
                $cancelableAt = $this->cancelableAt($pending);

                if ($this->clock->now() < $cancelableAt) {
                    throw InvoiceNotCancelable::pendingPayment($cancelableAt);
                }

                $this->cancelPendingPayment($pending);
            }
        }

        $invoice->cancel();
        $this->invoices->save($invoice);
    }

    public function cancelableAtForInvoice(string $invoiceId, string $accountId): ?DateTimeImmutable
    {
        $invoice = $this->invoices->findById($invoiceId);

        if ($invoice === null || ! $invoice->isPayable() || $invoice->accountId !== $accountId) {
            return null;
        }

        $pending = $this->payments->findLatestPendingByInvoiceId($invoiceId);

        if ($pending === null || $pending->status->isFinal()) {
            return null;
        }

        $cancelableAt = $this->cancelableAt($pending);

        return $this->clock->now() < $cancelableAt ? $cancelableAt : null;
    }

    private function cancelableAt(Payment $payment): DateTimeImmutable
    {
        $createdAt = $payment->createdAt ?? $this->clock->now();

        return $createdAt->modify('+'.self::CANCEL_GRACE_SECONDS.' seconds');
    }

    private function cancelPendingPayment(Payment $payment): void
    {
        if ($payment->mpPaymentId !== null) {
            try {
                $this->gateway->cancelPayment($payment->mpPaymentId);
            } catch (PaymentGatewayError) {
                // Encerra localmente mesmo se o MP recusar o cancelamento.
            }
        }

        $payment->updateStatus(PaymentStatus::Cancelled);
        $this->payments->save($payment);
    }
}

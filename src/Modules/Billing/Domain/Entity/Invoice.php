<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Entity;

use DateTimeImmutable;
use Src\Modules\Billing\Domain\ValueObject\InvoiceStatus;

/**
 * Fatura de um ciclo de assinatura (ou avulsa). Quando paga, dispara a
 * recarga de saldo do plano na carteira.
 *
 * @property list<InvoiceItem> $items
 */
final class Invoice
{
    /** @param list<InvoiceItem> $items */
    public function __construct(
        public readonly string $id,
        public readonly string $accountId,
        public ?string $subscriptionId,
        public InvoiceStatus $status,
        public readonly int $amountCents,
        public string $currency = 'BRL',
        public ?string $description = null,
        public ?DateTimeImmutable $dueDate = null,
        public ?DateTimeImmutable $periodStart = null,
        public ?DateTimeImmutable $periodEnd = null,
        public ?DateTimeImmutable $paidAt = null,
        public ?string $paymentId = null,
        public array $items = [],
        public array $metadata = [],
        public ?DateTimeImmutable $createdAt = null,
        public ?string $number = null,
    ) {}

    public function markPaid(string $paymentId, ?DateTimeImmutable $paidAt = null): void
    {
        $this->status = InvoiceStatus::Paid;
        $this->paymentId = $paymentId;
        $this->paidAt = $paidAt ?? new DateTimeImmutable;
    }

    public function markOverdue(): void
    {
        if ($this->status === InvoiceStatus::Open) {
            $this->status = InvoiceStatus::Overdue;
        }
    }

    public function cancel(): void
    {
        $this->status = InvoiceStatus::Canceled;
    }

    public function isPayable(): bool
    {
        return in_array($this->status, [InvoiceStatus::Open, InvoiceStatus::Overdue], true);
    }
}

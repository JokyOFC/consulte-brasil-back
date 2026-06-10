<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Entity;

use DateTimeImmutable;
use Src\Modules\Billing\Domain\ValueObject\PaymentMethod;
use Src\Modules\Billing\Domain\ValueObject\PaymentStatus;
use Src\Modules\Billing\Domain\ValueObject\PaymentType;

/**
 * Pagamento criado no Mercado Pago (recarga de saldo ou fatura). Guarda os
 * dados de exibição do checkout transparente (PIX/boleto) e o vínculo com o
 * pagamento no provedor para reconciliação via webhook.
 */
final class Payment
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string $id,
        public readonly string $accountId,
        public readonly PaymentType $type,
        public readonly PaymentMethod $method,
        public PaymentStatus $status,
        public readonly int $amountCents,
        public string $currency = 'BRL',
        public ?string $invoiceId = null,
        public ?string $mpPaymentId = null,
        public ?string $mpPreapprovalId = null,
        public ?string $qrCode = null,
        public ?string $qrCodeBase64 = null,
        public ?string $ticketUrl = null,
        public ?string $barcode = null,
        public ?string $description = null,
        public ?DateTimeImmutable $expiresAt = null,
        public ?DateTimeImmutable $paidAt = null,
        public array $metadata = [],
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    public function markApproved(?DateTimeImmutable $paidAt = null): void
    {
        $this->status = PaymentStatus::Approved;
        $this->paidAt = $paidAt ?? new DateTimeImmutable;
    }

    public function updateStatus(PaymentStatus $status): void
    {
        $this->status = $status;
    }
}

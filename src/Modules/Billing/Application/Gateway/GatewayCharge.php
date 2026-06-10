<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\Gateway;

use DateTimeImmutable;

/**
 * Resultado de uma cobrança criada no gateway, com os dados de exibição do
 * checkout transparente (PIX/boleto) quando aplicável.
 */
final readonly class GatewayCharge
{
    public function __construct(
        public string $mpPaymentId,
        public string $status,
        public ?string $qrCode = null,
        public ?string $qrCodeBase64 = null,
        public ?string $ticketUrl = null,
        public ?string $barcode = null,
        public ?DateTimeImmutable $expiresAt = null,
    ) {}
}

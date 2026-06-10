<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\Gateway;

use DateTimeImmutable;

/**
 * Estado de um pagamento consultado no gateway (fonte da verdade para o
 * webhook). O `status` é o status bruto do Mercado Pago.
 */
final readonly class GatewayPaymentStatus
{
    public function __construct(
        public string $mpPaymentId,
        public string $status,
        public int $amountCents,
        public ?string $externalReference = null,
        public ?DateTimeImmutable $paidAt = null,
    ) {}
}

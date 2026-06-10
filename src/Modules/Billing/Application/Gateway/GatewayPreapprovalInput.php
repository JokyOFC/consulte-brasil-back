<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\Gateway;

/**
 * Dados para criar uma assinatura recorrente (Preapproval) com cobrança
 * automática no cartão.
 */
final readonly class GatewayPreapprovalInput
{
    public function __construct(
        public int $amountCents,
        public string $reason,
        public string $payerEmail,
        public string $externalReference,
        public string $backUrl,
        public ?string $cardTokenId = null,
        public int $frequency = 1,
        public string $frequencyType = 'months',
    ) {}
}

<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\DTO;

use Src\Modules\Billing\Domain\ValueObject\PaymentMethod;

final readonly class SubscribeToPlanInput
{
    public function __construct(
        public string $accountId,
        public string $planId,
        public PaymentMethod $method,
        public string $payerEmail,
        public string $backUrl,
        // Token de cartão (Bricks) → habilita cobrança recorrente automática.
        public ?string $cardToken = null,
    ) {}
}

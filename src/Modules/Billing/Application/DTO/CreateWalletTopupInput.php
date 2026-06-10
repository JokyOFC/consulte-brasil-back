<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\DTO;

use Src\Modules\Billing\Domain\ValueObject\PaymentMethod;

final readonly class CreateWalletTopupInput
{
    public function __construct(
        public string $accountId,
        public int $amountCents,
        public PaymentMethod $method,
        public string $payerEmail,
        // Cartão (tokenizado no front via Bricks/MP.js).
        public ?string $cardToken = null,
        public int $installments = 1,
        public ?string $paymentMethodId = null,
        public ?string $issuerId = null,
    ) {}
}

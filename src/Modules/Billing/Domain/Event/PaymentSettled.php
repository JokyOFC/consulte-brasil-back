<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Event;

use Src\Modules\Billing\Domain\ValueObject\PaymentType;

/**
 * Pagamento aprovado e liquidado pela primeira vez (créditos concedidos).
 */
final readonly class PaymentSettled
{
    public function __construct(
        public string $paymentId,
        public string $accountId,
        public PaymentType $type,
        public int $amountCents,
        public int $creditsGranted,
    ) {}
}

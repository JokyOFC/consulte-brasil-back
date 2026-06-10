<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Exception;

use Src\Shared\Domain\Exception\DomainException;

final class PaymentGatewayError extends DomainException
{
    public static function from(string $message): self
    {
        return new self("Payment gateway error: {$message}");
    }
}

<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\ValueObject;

use Src\Shared\Domain\Exception\InvalidArgumentException;
use Stringable;

/**
 * Correlaciona as 3 etapas do fluxo de cobrança: a reserva e o seu
 * desfecho (commit ou refund) compartilham o mesmo ReservationId.
 */
final readonly class ReservationId implements Stringable
{
    public function __construct(public string $value)
    {
        if ($this->value === '') {
            throw new InvalidArgumentException('ReservationId cannot be empty.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

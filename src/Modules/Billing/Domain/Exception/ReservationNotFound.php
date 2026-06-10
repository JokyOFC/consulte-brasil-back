<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Exception;

use Src\Shared\Domain\Exception\DomainException;

final class ReservationNotFound extends DomainException
{
    public static function withId(string $reservationId): self
    {
        return new self("Reservation [{$reservationId}] was not found.");
    }
}

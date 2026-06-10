<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\DTO;

final readonly class ReservationResult
{
    public function __construct(
        public string $reservationId,
        public int $availableAfter,
    ) {}
}

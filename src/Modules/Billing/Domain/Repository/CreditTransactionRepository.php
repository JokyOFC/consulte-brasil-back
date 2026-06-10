<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Repository;

use Src\Modules\Billing\Domain\Entity\CreditTransaction;
use Src\Modules\Billing\Domain\ValueObject\ReservationId;

interface CreditTransactionRepository
{
    public function append(CreditTransaction $transaction): void;

    public function findByIdempotencyKey(string $idempotencyKey): ?CreditTransaction;

    /** Localiza o lançamento de reserva original. */
    public function findReserve(ReservationId $reservationId): ?CreditTransaction;

    /** Já existe um desfecho (commit ou refund) para esta reserva? */
    public function hasSettlement(ReservationId $reservationId): bool;
}

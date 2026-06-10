<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Entity;

use DateTimeImmutable;
use Src\Modules\Billing\Domain\ValueObject\ReservationId;
use Src\Modules\Billing\Domain\ValueObject\TransactionDirection;
use Src\Modules\Billing\Domain\ValueObject\TransactionType;
use Src\Modules\Billing\Domain\ValueObject\WalletId;
use Src\Shared\Domain\ValueObject\CreditAmount;

/**
 * Lançamento do ledger — imutável e append-only. Nunca é alterado nem
 * removido; correções são novos lançamentos (adjustment).
 */
final readonly class CreditTransaction
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $id,
        public string $accountId,
        public WalletId $walletId,
        public TransactionType $type,
        public TransactionDirection $direction,
        public CreditAmount $amount,
        public CreditAmount $balanceAfter,
        public ?ReservationId $reservationId = null,
        public ?string $referenceType = null,
        public ?string $referenceId = null,
        public ?string $idempotencyKey = null,
        public array $metadata = [],
        public ?DateTimeImmutable $createdAt = null,
    ) {}
}

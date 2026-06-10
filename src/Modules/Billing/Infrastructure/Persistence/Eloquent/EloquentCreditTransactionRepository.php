<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Persistence\Eloquent;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Src\Modules\Billing\Domain\Entity\CreditTransaction;
use Src\Modules\Billing\Domain\Repository\CreditTransactionRepository;
use Src\Modules\Billing\Domain\ValueObject\ReservationId;
use Src\Modules\Billing\Domain\ValueObject\TransactionDirection;
use Src\Modules\Billing\Domain\ValueObject\TransactionType;
use Src\Modules\Billing\Domain\ValueObject\WalletId;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\CreditTransactionModel;
use Src\Shared\Domain\ValueObject\CreditAmount;

final class EloquentCreditTransactionRepository implements CreditTransactionRepository
{
    public function append(CreditTransaction $transaction): void
    {
        $model = new CreditTransactionModel;

        $model->id = $transaction->id;
        $model->account_id = $transaction->accountId;
        $model->wallet_id = $transaction->walletId->value;
        $model->type = $transaction->type->value;
        $model->direction = $transaction->direction->value;
        $model->amount = $transaction->amount->value;
        $model->balance_after = $transaction->balanceAfter->value;
        $model->reservation_id = $transaction->reservationId?->value;
        $model->reference_type = $transaction->referenceType;
        $model->reference_id = $transaction->referenceId;
        $model->idempotency_key = $transaction->idempotencyKey;
        $model->metadata = $transaction->metadata;
        $model->created_at = $transaction->createdAt ?? new DateTimeImmutable;
        $model->save();
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?CreditTransaction
    {
        $model = CreditTransactionModel::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function findReserve(ReservationId $reservationId): ?CreditTransaction
    {
        $model = CreditTransactionModel::query()
            ->where('reservation_id', $reservationId->value)
            ->where('type', TransactionType::Reserve->value)
            ->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function hasSettlement(ReservationId $reservationId): bool
    {
        return CreditTransactionModel::query()
            ->where('reservation_id', $reservationId->value)
            ->whereIn('type', [TransactionType::Commit->value, TransactionType::Refund->value])
            ->exists();
    }

    private function toEntity(CreditTransactionModel $model): CreditTransaction
    {
        return new CreditTransaction(
            id: $model->id,
            accountId: $model->account_id,
            walletId: new WalletId($model->wallet_id),
            type: TransactionType::from($model->type),
            direction: TransactionDirection::from($model->direction),
            amount: CreditAmount::of($model->amount),
            balanceAfter: CreditAmount::of($model->balance_after),
            reservationId: $model->reservation_id !== null ? new ReservationId($model->reservation_id) : null,
            referenceType: $model->reference_type,
            referenceId: $model->reference_id,
            idempotencyKey: $model->idempotency_key,
            metadata: $model->metadata ?? [],
            createdAt: $model->created_at instanceof Carbon
                ? DateTimeImmutable::createFromInterface($model->created_at)
                : null,
        );
    }
}

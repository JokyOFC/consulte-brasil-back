<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Persistence\Eloquent;

use Src\Modules\Billing\Domain\Entity\Wallet;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Billing\Domain\ValueObject\WalletId;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\WalletModel;
use Src\Shared\Domain\ValueObject\CreditAmount;

final class EloquentWalletRepository implements WalletRepository
{
    public function findByAccountId(string $accountId): ?Wallet
    {
        $model = WalletModel::query()->where('account_id', $accountId)->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function findByAccountIdForUpdate(string $accountId): ?Wallet
    {
        $model = WalletModel::query()
            ->where('account_id', $accountId)
            ->lockForUpdate()
            ->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function save(Wallet $wallet): void
    {
        $model = WalletModel::find($wallet->id->value) ?? new WalletModel;

        $model->id = $wallet->id->value;
        $model->account_id = $wallet->accountId;
        $model->balance = $wallet->balance()->value;
        $model->reserved = $wallet->reserved()->value;
        $model->save();
    }

    public function all(): array
    {
        return WalletModel::query()
            ->get()
            ->map(fn (WalletModel $model): Wallet => $this->toEntity($model))
            ->all();
    }

    private function toEntity(WalletModel $model): Wallet
    {
        return new Wallet(
            new WalletId($model->id),
            $model->account_id,
            CreditAmount::of($model->balance),
            CreditAmount::of($model->reserved),
        );
    }
}

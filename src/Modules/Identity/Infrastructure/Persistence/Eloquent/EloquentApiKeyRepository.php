<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Infrastructure\Persistence\Eloquent;

use DateTimeImmutable;
use Src\Modules\Identity\Domain\Entity\ApiKey;
use Src\Modules\Identity\Domain\Repository\ApiKeyRepository;
use Src\Modules\Identity\Domain\ValueObject\AccountId;
use Src\Modules\Identity\Domain\ValueObject\ApiKeyId;
use Src\Modules\Identity\Domain\ValueObject\ApiKeyStatus;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\ApiKeyModel;

final class EloquentApiKeyRepository implements ApiKeyRepository
{
    public function save(ApiKey $apiKey): void
    {
        $model = ApiKeyModel::find($apiKey->id->value) ?? new ApiKeyModel;

        $model->id = $apiKey->id->value;
        $model->account_id = $apiKey->accountId->value;
        $model->name = $apiKey->name;
        $model->prefix = $apiKey->prefix;
        $model->key_hash = $apiKey->keyHash;
        $model->last_four = $apiKey->lastFour;
        $model->scopes = $apiKey->scopes;
        $model->status = $apiKey->status->value;
        $model->last_used_at = $apiKey->lastUsedAt;
        $model->expires_at = $apiKey->expiresAt;
        $model->save();
    }

    public function findById(ApiKeyId $id): ?ApiKey
    {
        $model = ApiKeyModel::find($id->value);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function findByPrefix(string $prefix): ?ApiKey
    {
        $model = ApiKeyModel::query()->where('prefix', $prefix)->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function listForAccount(AccountId $accountId): array
    {
        return ApiKeyModel::query()
            ->where('account_id', $accountId->value)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ApiKeyModel $model): ApiKey => $this->toEntity($model))
            ->all();
    }

    private function toEntity(ApiKeyModel $model): ApiKey
    {
        return new ApiKey(
            id: new ApiKeyId($model->id),
            accountId: new AccountId($model->account_id),
            name: $model->name,
            prefix: $model->prefix,
            keyHash: $model->key_hash,
            lastFour: $model->last_four,
            scopes: $model->scopes ?? [],
            status: ApiKeyStatus::from($model->status),
            lastUsedAt: $model->last_used_at !== null
                ? DateTimeImmutable::createFromInterface($model->last_used_at)
                : null,
            expiresAt: $model->expires_at !== null
                ? DateTimeImmutable::createFromInterface($model->expires_at)
                : null,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}

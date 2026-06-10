<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Infrastructure\Persistence\Eloquent;

use DateTimeImmutable;
use Src\Modules\Identity\Domain\Entity\Account;
use Src\Modules\Identity\Domain\Repository\AccountRepository;
use Src\Modules\Identity\Domain\ValueObject\AccountId;
use Src\Modules\Identity\Domain\ValueObject\AccountStatus;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;
use Src\Shared\Domain\ValueObject\Document;

final class EloquentAccountRepository implements AccountRepository
{
    public function save(Account $account): void
    {
        $model = AccountModel::find($account->id->value) ?? new AccountModel;

        $model->id = $account->id->value;
        $model->name = $account->name;
        $model->document = $account->document->value;
        $model->document_type = $account->document->type;
        $model->status = $account->status->value;
        $model->save();
    }

    public function findById(AccountId $id): ?Account
    {
        $model = AccountModel::find($id->value);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function findByDocument(Document $document): ?Account
    {
        $model = AccountModel::query()->where('document', $document->value)->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    private function toEntity(AccountModel $model): Account
    {
        return new Account(
            id: new AccountId($model->id),
            name: $model->name,
            document: Document::fromString($model->document),
            status: AccountStatus::from($model->status),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}

<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Domain\Repository;

use Src\Modules\Identity\Domain\Entity\ApiKey;
use Src\Modules\Identity\Domain\ValueObject\AccountId;
use Src\Modules\Identity\Domain\ValueObject\ApiKeyId;

interface ApiKeyRepository
{
    public function save(ApiKey $apiKey): void;

    public function findById(ApiKeyId $id): ?ApiKey;

    public function findByPrefix(string $prefix): ?ApiKey;

    /** @return list<ApiKey> */
    public function listForAccount(AccountId $accountId): array;
}

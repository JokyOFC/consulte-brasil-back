<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Application\UseCase;

use Src\Modules\Identity\Domain\Exception\ApiKeyNotFound;
use Src\Modules\Identity\Domain\Repository\ApiKeyRepository;
use Src\Modules\Identity\Domain\ValueObject\AccountId;
use Src\Modules\Identity\Domain\ValueObject\ApiKeyId;

final readonly class RevokeApiKey
{
    public function __construct(
        private ApiKeyRepository $apiKeys,
    ) {}

    public function handle(string $accountId, string $apiKeyId): void
    {
        $apiKey = $this->apiKeys->findById(new ApiKeyId($apiKeyId));

        // Confere posse: a chave precisa pertencer à conta que solicita a revogação.
        if ($apiKey === null || ! $apiKey->accountId->equals(new AccountId($accountId))) {
            throw ApiKeyNotFound::withId($apiKeyId);
        }

        $apiKey->revoke();
        $this->apiKeys->save($apiKey);
    }
}

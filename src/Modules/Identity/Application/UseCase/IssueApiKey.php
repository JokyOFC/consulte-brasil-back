<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Application\UseCase;

use Src\Modules\Identity\Application\DTO\IssueApiKeyInput;
use Src\Modules\Identity\Application\DTO\IssuedApiKey;
use Src\Modules\Identity\Application\Port\ApiKeyHasher;
use Src\Modules\Identity\Application\Port\TokenGenerator;
use Src\Modules\Identity\Domain\Entity\ApiKey;
use Src\Modules\Identity\Domain\Exception\AccountNotFound;
use Src\Modules\Identity\Domain\Repository\AccountRepository;
use Src\Modules\Identity\Domain\Repository\ApiKeyRepository;
use Src\Modules\Identity\Domain\ValueObject\AccountId;
use Src\Modules\Identity\Domain\ValueObject\ApiKeyId;
use Src\Modules\Identity\Domain\ValueObject\ApiKeyStatus;
use Src\Modules\Identity\Domain\ValueObject\ApiKeyToken;
use Src\Shared\Application\Contracts\Clock;
use Src\Shared\Application\Contracts\IdGenerator;

final readonly class IssueApiKey
{
    public function __construct(
        private AccountRepository $accounts,
        private ApiKeyRepository $apiKeys,
        private TokenGenerator $tokens,
        private ApiKeyHasher $hasher,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    public function handle(IssueApiKeyInput $input): IssuedApiKey
    {
        $accountId = new AccountId($input->accountId);

        if ($this->accounts->findById($accountId) === null) {
            throw AccountNotFound::withId($accountId);
        }

        $token = new ApiKeyToken($this->tokens->generateSecret());

        $apiKey = new ApiKey(
            id: new ApiKeyId($this->ids->generate()),
            accountId: $accountId,
            name: $input->name,
            prefix: $token->prefix(),
            keyHash: $this->hasher->hash($token->secret),
            lastFour: $token->lastFour(),
            scopes: $input->scopes,
            status: ApiKeyStatus::Active,
            lastUsedAt: null,
            expiresAt: $input->expiresAt,
            createdAt: $this->clock->now(),
        );

        $this->apiKeys->save($apiKey);

        return new IssuedApiKey($apiKey, $token->full());
    }
}

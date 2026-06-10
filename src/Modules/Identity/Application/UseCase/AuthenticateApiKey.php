<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Application\UseCase;

use Src\Modules\Identity\Application\DTO\AuthenticatedApiKey;
use Src\Modules\Identity\Application\Port\ApiKeyHasher;
use Src\Modules\Identity\Domain\Repository\AccountRepository;
use Src\Modules\Identity\Domain\Repository\ApiKeyRepository;
use Src\Modules\Identity\Domain\ValueObject\ApiKeyToken;
use Src\Shared\Application\Contracts\Clock;

/**
 * Resolve um token da API pública para o par (chave, conta), aplicando
 * todas as verificações: formato, existência, hash, validade e status.
 *
 * Retorna null em qualquer falha (o guard traduz null em 401), sem vazar
 * qual etapa falhou.
 */
final readonly class AuthenticateApiKey
{
    public function __construct(
        private ApiKeyRepository $apiKeys,
        private AccountRepository $accounts,
        private ApiKeyHasher $hasher,
        private Clock $clock,
    ) {}

    public function authenticate(string $rawToken): ?AuthenticatedApiKey
    {
        $token = ApiKeyToken::parse($rawToken);

        if ($token === null) {
            return null;
        }

        $apiKey = $this->apiKeys->findByPrefix($token->prefix());

        if ($apiKey === null || ! $this->hasher->verify($token->secret, $apiKey->keyHash)) {
            return null;
        }

        if (! $apiKey->isUsable($this->clock->now())) {
            return null;
        }

        $account = $this->accounts->findById($apiKey->accountId);

        if ($account === null || ! $account->isActive()) {
            return null;
        }

        return new AuthenticatedApiKey($apiKey, $account);
    }
}

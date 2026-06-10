<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Domain\Entity;

use DateTimeImmutable;
use Src\Modules\Identity\Domain\ValueObject\AccountId;
use Src\Modules\Identity\Domain\ValueObject\ApiKeyId;
use Src\Modules\Identity\Domain\ValueObject\ApiKeyStatus;

/**
 * Credencial que o cliente usa para autenticar na API pública.
 *
 * O segredo em texto puro NUNCA é persistido: guardamos apenas o hash
 * ($keyHash), um prefixo público de lookup ($prefix) e os últimos
 * caracteres ($lastFour) para exibição no painel.
 */
final class ApiKey
{
    /** @param list<string> $scopes */
    public function __construct(
        public readonly ApiKeyId $id,
        public readonly AccountId $accountId,
        public string $name,
        public readonly string $prefix,
        public readonly string $keyHash,
        public readonly string $lastFour,
        public array $scopes,
        public ApiKeyStatus $status,
        public ?DateTimeImmutable $lastUsedAt,
        public readonly ?DateTimeImmutable $expiresAt,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public function revoke(): void
    {
        $this->status = ApiKeyStatus::Revoked;
    }

    public function markUsed(DateTimeImmutable $now): void
    {
        $this->lastUsedAt = $now;
    }

    public function isUsable(DateTimeImmutable $now): bool
    {
        if ($this->status !== ApiKeyStatus::Active) {
            return false;
        }

        return $this->expiresAt === null || $this->expiresAt > $now;
    }

    /**
     * Uma chave sem escopos (ou com o coringa "*") tem acesso total.
     */
    public function allows(string $scope): bool
    {
        return $this->scopes === []
            || in_array('*', $this->scopes, true)
            || in_array($scope, $this->scopes, true);
    }
}

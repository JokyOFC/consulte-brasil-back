<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Domain\Entity;

use DateTimeImmutable;
use Src\Modules\Identity\Domain\ValueObject\AccountId;
use Src\Modules\Identity\Domain\ValueObject\AccountStatus;
use Src\Shared\Domain\ValueObject\Document;

/**
 * Conta = cliente/tenant pagante. Raiz do agregado Identity ao qual se
 * vinculam usuários, chaves de API e (no Billing) a carteira de créditos.
 */
final class Account
{
    public function __construct(
        public readonly AccountId $id,
        public string $name,
        public readonly Document $document,
        public AccountStatus $status,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function register(
        AccountId $id,
        string $name,
        Document $document,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $name, $document, AccountStatus::Active, $now);
    }

    public function suspend(): void
    {
        $this->status = AccountStatus::Suspended;
    }

    public function activate(): void
    {
        $this->status = AccountStatus::Active;
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }
}

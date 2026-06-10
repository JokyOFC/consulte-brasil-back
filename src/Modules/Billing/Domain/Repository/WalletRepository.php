<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Repository;

use Src\Modules\Billing\Domain\Entity\Wallet;

interface WalletRepository
{
    public function findByAccountId(string $accountId): ?Wallet;

    /**
     * Carrega a carteira com lock de linha (SELECT ... FOR UPDATE) para
     * serializar reservas concorrentes — o coração do controle de
     * concorrência ACID. Deve ser chamado dentro de uma transação.
     */
    public function findByAccountIdForUpdate(string $accountId): ?Wallet;

    public function save(Wallet $wallet): void;

    /** @return list<Wallet> */
    public function all(): array;
}

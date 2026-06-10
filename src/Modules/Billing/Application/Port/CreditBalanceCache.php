<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\Port;

/**
 * Cache do saldo DISPONÍVEL por conta (acelerador de leitura, ex.: Redis).
 *
 * NÃO é a fonte da verdade — apenas um espelho do estado autoritativo do
 * MySQL, mantido em sincronia pelas operações e pelo job de reconciliação.
 */
interface CreditBalanceCache
{
    public function setAvailable(string $accountId, int $available): void;

    public function increment(string $accountId, int $amount): void;

    public function decrement(string $accountId, int $amount): void;

    public function getAvailable(string $accountId): ?int;
}

<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Repository;
use Src\Modules\Billing\Application\Port\CreditBalanceCache;

/**
 * Implementa o cache de saldo sobre o contrato de Cache do Laravel — em
 * produção aponta para o Redis (CACHE_STORE=redis); nos testes usa o store
 * "array". Mesma classe, zero ramificação por ambiente.
 *
 * increment/decrement só atuam se a chave já foi hidratada; caso contrário
 * a reconciliação (billing:reconcile-balances) define o valor inicial a
 * partir do estado autoritativo do MySQL.
 */
final class CacheCreditBalanceCache implements CreditBalanceCache
{
    public function __construct(private Repository $cache) {}

    public function setAvailable(string $accountId, int $available): void
    {
        $this->cache->forever($this->key($accountId), $available);
    }

    public function increment(string $accountId, int $amount): void
    {
        if ($this->cache->get($this->key($accountId)) === null) {
            return;
        }

        $this->cache->increment($this->key($accountId), $amount);
    }

    public function decrement(string $accountId, int $amount): void
    {
        if ($this->cache->get($this->key($accountId)) === null) {
            return;
        }

        $this->cache->decrement($this->key($accountId), $amount);
    }

    public function getAvailable(string $accountId): ?int
    {
        $value = $this->cache->get($this->key($accountId));

        return $value === null ? null : (int) $value;
    }

    private function key(string $accountId): string
    {
        return "wallet:{$accountId}:available";
    }
}

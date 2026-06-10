<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Repository;
use Src\Modules\Provider\Domain\Port\CircuitBreaker;

/**
 * Circuit breaker simples sobre o contrato de Cache:
 *
 *   - Cada falha incrementa um contador (TTL curto).
 *   - Ao atingir o threshold, marca o circuito como "open" por openSeconds.
 *   - Enquanto open, isOpen() retorna true e o router pula o provedor.
 *   - Um sucesso zera o contador e fecha o circuito.
 *
 * Em produção: cache=redis → contador atômico distribuído.
 * Em testes:   cache=array → ainda atômico no processo único.
 */
final class CacheCircuitBreaker implements CircuitBreaker
{
    public function __construct(
        private Repository $cache,
        private int $failureThreshold = 5,
        private int $openSeconds = 60,
        private int $failureWindowSeconds = 120,
    ) {}

    public function isOpen(string $providerId, string $queryType): bool
    {
        return (bool) $this->cache->get($this->openKey($providerId, $queryType), false);
    }

    public function recordSuccess(string $providerId, string $queryType): void
    {
        $this->cache->forget($this->failureKey($providerId, $queryType));
        $this->cache->forget($this->openKey($providerId, $queryType));
    }

    public function recordFailure(string $providerId, string $queryType): void
    {
        $failureKey = $this->failureKey($providerId, $queryType);

        if ($this->cache->get($failureKey) === null) {
            $this->cache->put($failureKey, 1, $this->failureWindowSeconds);
        } else {
            $this->cache->increment($failureKey);
        }

        $failures = (int) $this->cache->get($failureKey, 0);

        if ($failures >= $this->failureThreshold) {
            $this->cache->put($this->openKey($providerId, $queryType), true, $this->openSeconds);
        }
    }

    private function failureKey(string $providerId, string $queryType): string
    {
        return "cb:failures:{$providerId}:{$queryType}";
    }

    private function openKey(string $providerId, string $queryType): string
    {
        return "cb:open:{$providerId}:{$queryType}";
    }
}

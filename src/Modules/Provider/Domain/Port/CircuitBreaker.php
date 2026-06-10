<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Domain\Port;

/**
 * Disjuntor por (provider × queryType). Depois de N falhas em sequência,
 * o circuito "abre" por T segundos — o router pula o provedor sem tentar.
 */
interface CircuitBreaker
{
    public function isOpen(string $providerId, string $queryType): bool;

    public function recordSuccess(string $providerId, string $queryType): void;

    public function recordFailure(string $providerId, string $queryType): void;
}

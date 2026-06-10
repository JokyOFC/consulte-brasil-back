<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Domain\Port;

use Src\Modules\Provider\Domain\ValueObject\ProviderDescriptor;

/**
 * Catálogo administrável de provedores. Reflete o toggle do admin
 * (enabled/disabled) e a ordem de prioridade por tipo de consulta.
 *
 * O ProviderRouter consulta este registry, NÃO a entidade Provider direta.
 */
interface ProviderRegistry
{
    /**
     * Provedores HABILITADOS (provider + capability) para o tipo dado,
     * já ordenados por prioridade ascendente (menor = tenta primeiro).
     *
     * @return list<ProviderDescriptor>
     */
    public function enabledFor(string $queryType): array;
}

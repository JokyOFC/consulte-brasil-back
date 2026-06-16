<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Domain\Port;

use Src\Modules\Consultation\Domain\ValueObject\QueryType;

/**
 * Catálogo dinâmico de tipos de consulta. Diz se um código é válido e
 * qual o custo default — quando nenhuma capability define um custo
 * específico por provedor.
 */
interface QueryTypeCatalog
{
    public function exists(QueryType $type): bool;

    public function defaultCreditCost(QueryType $type): int;

    /** TTL do cache em segundos (0 = desabilitado para este tipo). */
    public function cacheTtlSeconds(QueryType $type): int;
}

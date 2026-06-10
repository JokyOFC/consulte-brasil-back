<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Application\Port;

use Src\Modules\Consultation\Domain\Port\DataProviderPort;

/**
 * Resolve um identifier ("api_brasil") para a instância de adapter
 * correspondente — bind feito pelo container (tag).
 * Retorna null quando não há adapter registrado para o identifier.
 */
interface ProviderResolver
{
    public function resolve(string $identifier): ?DataProviderPort;
}

<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Domain\Exception;

use Src\Shared\Domain\Exception\DomainException;

/**
 * Todos os provedores habilitados falharam em cascata. O orquestrador
 * (ExecuteConsultation) reage estornando a reserva de créditos.
 */
final class AllProvidersFailed extends DomainException
{
    public static function forType(string $queryType): self
    {
        return new self("All providers failed for query type [{$queryType}].");
    }
}

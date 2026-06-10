<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Domain\Exception;

use Src\Shared\Domain\Exception\DomainException;

/**
 * Nenhum provedor está habilitado para este tipo de consulta (admin
 * desligou todos, ou o catálogo está vazio). Traduzido para HTTP 503.
 */
final class NoProviderAvailable extends DomainException
{
    public static function forType(string $queryType): self
    {
        return new self("No provider is enabled for query type [{$queryType}].");
    }
}

<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Domain\Exception;

use Src\Shared\Domain\Exception\DomainException;
use Throwable;

/**
 * Sinaliza que um provedor específico falhou — o router deve tentar o
 * próximo. Lançar isto NÃO falha a consulta inteira, só dispara o failover.
 */
final class ProviderUnavailable extends DomainException
{
    public function __construct(public readonly string $providerIdentifier, ?Throwable $previous = null)
    {
        parent::__construct("Provider [{$providerIdentifier}] is unavailable.", previous: $previous);
    }
}

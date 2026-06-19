<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Domain\Exception;

use Src\Shared\Domain\Exception\DomainException;
use Throwable;

/**
 * Sinaliza que um provedor específico falhou — o router deve tentar o
 * próximo. Lançar isto NÃO falha a consulta inteira, só dispara o failover.
 *
 * $transient distingue a NATUREZA da falha para o circuit breaker:
 *  - true  → outage temporário e abrangente (timeout, 5xx, conexão). Vale
 *            contar como falha e abrir o circuito para proteger o provedor.
 *  - false → rejeição de negócio/config por requisição (4xx, "consulta não
 *            concluída", documento não encontrado, token ausente). O provedor
 *            está saudável; NÃO se deve abrir o circuito (senão um CPF que
 *            "não concluiu" derruba o serviço para todos).
 */
final class ProviderUnavailable extends DomainException
{
    /**
     * @param  array<string, mixed>  $context  detalhe de diagnóstico (ex.: corpo do erro do provedor)
     */
    public function __construct(
        public readonly string $providerIdentifier,
        public readonly ?string $reason = null,
        ?Throwable $previous = null,
        public readonly bool $transient = true,
        public readonly array $context = [],
    ) {
        $message = $reason !== null && $reason !== ''
            ? "Provider [{$providerIdentifier}] is unavailable: {$reason}"
            : "Provider [{$providerIdentifier}] is unavailable.";

        parent::__construct($message, previous: $previous);
    }
}

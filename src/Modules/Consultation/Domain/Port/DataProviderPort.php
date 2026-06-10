<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Domain\Port;

use Src\Modules\Consultation\Domain\ValueObject\ConsultationRequest;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationResult;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;

/**
 * O único contrato que o Core conhece sobre "fornecedores de dados".
 *
 * Adicionar um novo provedor = implementar este port + 1 linha de tag no
 * container. Zero mudança no domínio ou casos de uso.
 */
interface DataProviderPort
{
    public function identifier(): string;

    public function supports(QueryType $type): bool;

    /**
     * Executa a consulta. Em caso de falha do upstream (timeout, 5xx,
     * dado indisponível) DEVE lançar ProviderUnavailable para o router
     * acionar o failover. Erros de domínio (input inválido) NÃO devem
     * disparar failover — lance outra coisa.
     */
    public function fetch(ConsultationRequest $request): ConsultationResult;
}

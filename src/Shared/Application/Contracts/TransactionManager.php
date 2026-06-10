<?php

declare(strict_types=1);

namespace Src\Shared\Application\Contracts;

/**
 * Port que abstrai a transação de banco. Mantém os casos de uso livres
 * da facade DB enquanto garante atomicidade (tudo ou nada) das operações
 * de ledger.
 */
interface TransactionManager
{
    /**
     * @template T
     *
     * @param  callable():T  $work
     * @return T
     */
    public function transactional(callable $work): mixed;
}

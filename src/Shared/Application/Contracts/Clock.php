<?php

declare(strict_types=1);

namespace Src\Shared\Application\Contracts;

use DateTimeImmutable;

/**
 * Port que abstrai "o tempo atual". Permite congelar o relógio em testes
 * sem acoplar o domínio ao now() do sistema.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}

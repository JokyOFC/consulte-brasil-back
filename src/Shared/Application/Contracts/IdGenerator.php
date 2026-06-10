<?php

declare(strict_types=1);

namespace Src\Shared\Application\Contracts;

/**
 * Port para geração de identificadores únicos (UUID por padrão).
 * Mantém o domínio livre de dependência direta de uma lib de UUID.
 */
interface IdGenerator
{
    public function generate(): string;
}

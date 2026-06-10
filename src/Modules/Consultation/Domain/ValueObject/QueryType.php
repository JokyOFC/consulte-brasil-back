<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Domain\ValueObject;

use Src\Shared\Domain\Exception\InvalidArgumentException;

/**
 * Tipo de consulta normalizado (cpf, cnpj, vehicle, ...).
 *
 * Não é um enum porque o catálogo é dinâmico — controlado pela tabela
 * "query_types" e administrável.
 */
final readonly class QueryType
{
    public function __construct(public string $code)
    {
        if (! preg_match('/^[a-z][a-z0-9_]{1,49}$/', $code)) {
            throw new InvalidArgumentException("Invalid query type code: {$code}.");
        }
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}

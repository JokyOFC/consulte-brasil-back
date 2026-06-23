<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Domain\Exception;

use Src\Shared\Domain\Exception\DomainException;

/**
 * O documento/identificador informado para a consulta é inválido (ex.: CPF ou
 * CNPJ com dígito verificador incorreto, ou campo obrigatório ausente).
 *
 * É uma rejeição de ENTRADA do usuário — não uma falha de provedor. Por isso é
 * lançada ANTES de reservar crédito e traduzida para HTTP 422 na borda, com uma
 * mensagem pronta para exibir ao usuário ($userMessage).
 */
final class InvalidDocument extends DomainException
{
    public function __construct(
        string $message,
        public readonly string $userMessage,
    ) {
        parent::__construct($message);
    }

    public static function invalidCpf(): self
    {
        return new self(
            'The provided document is not a valid CPF.',
            'O CPF informado é inválido. Confira os números e tente novamente.',
        );
    }

    public static function invalidCnpj(): self
    {
        return new self(
            'The provided document is not a valid CNPJ.',
            'O CNPJ informado é inválido. Confira os números e tente novamente.',
        );
    }

    /** Documento de tipo não-padronizado (CNH, RG, placa, etc.). */
    public static function invalid(string $label): self
    {
        return new self(
            "The provided document is not a valid {$label}.",
            "O {$label} informado é inválido. Confira os dados e tente novamente.",
        );
    }

    public static function required(string $label): self
    {
        return new self(
            "A {$label} is required for this query type.",
            "Informe um {$label} válido para realizar a consulta.",
        );
    }
}

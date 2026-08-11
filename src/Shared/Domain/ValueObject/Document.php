<?php

declare(strict_types=1);

namespace Src\Shared\Domain\ValueObject;

use Src\Shared\Domain\Exception\InvalidArgumentException;

/**
 * Documento de identificação de uma conta: CPF (pessoa física) ou CNPJ
 * (pessoa jurídica). Detecta o tipo pelo comprimento sem máscara — CPF tem
 * 11 dígitos, CNPJ tem 14 posições (alfanuméricas desde a IN RFB 2.229/2024)
 * — e delega a validação ao Value Object correspondente.
 */
final readonly class Document
{
    public const TYPE_CPF = 'cpf';

    public const TYPE_CNPJ = 'cnpj';

    private function __construct(
        public string $value,
        public string $type,
    ) {}

    public static function fromString(string $raw): self
    {
        $chars = preg_replace('/[^0-9A-Za-z]/', '', $raw) ?? '';

        return match (strlen($chars)) {
            11 => new self(Cpf::fromString($chars)->value, self::TYPE_CPF),
            14 => new self(Cnpj::fromString($chars)->value, self::TYPE_CNPJ),
            default => throw new InvalidArgumentException("Document must be a valid CPF or CNPJ: {$raw}."),
        };
    }

    public function isCompany(): bool
    {
        return $this->type === self::TYPE_CNPJ;
    }

    public function formatted(): string
    {
        return $this->isCompany()
            ? Cnpj::fromString($this->value)->formatted()
            : Cpf::fromString($this->value)->formatted();
    }
}

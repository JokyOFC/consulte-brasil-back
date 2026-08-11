<?php

declare(strict_types=1);

namespace Src\Shared\Domain\ValueObject;

use Src\Shared\Domain\Exception\InvalidArgumentException;

/**
 * CNPJ validado (armazenado sem máscara, em maiúsculas). Aceita o formato
 * alfanumérico da IN RFB 2.229/2024: 12 posições alfanuméricas seguidas de
 * 2 dígitos verificadores sempre numéricos.
 */
final readonly class Cnpj
{
    private const WEIGHTS_FIRST = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    private const WEIGHTS_SECOND = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    private function __construct(public string $value) {}

    public static function fromString(string $raw): self
    {
        $chars = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $raw) ?? '');

        if (! self::isValid($chars)) {
            throw new InvalidArgumentException("Invalid CNPJ: {$raw}.");
        }

        return new self($chars);
    }

    public function formatted(): string
    {
        return preg_replace('/([0-9A-Z]{2})([0-9A-Z]{3})([0-9A-Z]{3})([0-9A-Z]{4})([0-9]{2})/', '$1.$2.$3/$4-$5', $this->value);
    }

    private static function isValid(string $cnpj): bool
    {
        if (! preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $cnpj)) {
            return false;
        }

        // Sequências repetidas só invalidam o formato todo-numérico legado.
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        if ((int) $cnpj[12] !== self::checkDigit($cnpj, self::WEIGHTS_FIRST)) {
            return false;
        }

        return (int) $cnpj[13] === self::checkDigit($cnpj, self::WEIGHTS_SECOND);
    }

    /**
     * Valor de cada caractere segue a IN RFB 2.229/2024: ord(char) - 48
     * (dígitos mantêm 0-9; A=17 ... Z=42).
     *
     * @param  list<int>  $weights
     */
    private static function checkDigit(string $cnpj, array $weights): int
    {
        $sum = 0;
        foreach ($weights as $i => $weight) {
            $sum += (ord($cnpj[$i]) - 48) * $weight;
        }
        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}

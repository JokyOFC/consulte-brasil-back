<?php

declare(strict_types=1);

namespace Src\Shared\Domain\ValueObject;

use Src\Shared\Domain\Exception\InvalidArgumentException;

/**
 * CNPJ validado (somente dígitos armazenados).
 */
final readonly class Cnpj
{
    private const WEIGHTS_FIRST = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    private const WEIGHTS_SECOND = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    private function __construct(public string $value) {}

    public static function fromString(string $raw): self
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (! self::isValid($digits)) {
            throw new InvalidArgumentException("Invalid CNPJ: {$raw}.");
        }

        return new self($digits);
    }

    public function formatted(): string
    {
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $this->value);
    }

    private static function isValid(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14) {
            return false;
        }

        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        if ((int) $cnpj[12] !== self::checkDigit($cnpj, self::WEIGHTS_FIRST)) {
            return false;
        }

        return (int) $cnpj[13] === self::checkDigit($cnpj, self::WEIGHTS_SECOND);
    }

    /** @param list<int> $weights */
    private static function checkDigit(string $cnpj, array $weights): int
    {
        $sum = 0;
        foreach ($weights as $i => $weight) {
            $sum += (int) $cnpj[$i] * $weight;
        }
        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}

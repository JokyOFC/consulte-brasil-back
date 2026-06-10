<?php

declare(strict_types=1);

namespace Src\Shared\Domain\ValueObject;

use Src\Shared\Domain\Exception\InvalidArgumentException;

/**
 * CPF validado (somente dígitos armazenados).
 */
final readonly class Cpf
{
    private function __construct(public string $value) {}

    public static function fromString(string $raw): self
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (! self::isValid($digits)) {
            throw new InvalidArgumentException("Invalid CPF: {$raw}.");
        }

        return new self($digits);
    }

    public function formatted(): string
    {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $this->value);
    }

    private static function isValid(string $cpf): bool
    {
        if (strlen($cpf) !== 11) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($c = 0; $c < $t; $c++) {
                $sum += (int) $cpf[$c] * (($t + 1) - $c);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }
}

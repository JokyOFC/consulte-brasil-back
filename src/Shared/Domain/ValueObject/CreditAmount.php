<?php

declare(strict_types=1);

namespace Src\Shared\Domain\ValueObject;

use Src\Shared\Domain\Exception\InvalidArgumentException;

/**
 * Quantidade de créditos. Sempre um inteiro não-negativo — nunca float,
 * para evitar erros de arredondamento em dinheiro/uso.
 */
final readonly class CreditAmount
{
    private function __construct(public int $value) {}

    public static function of(int $value): self
    {
        if ($value < 0) {
            throw new InvalidArgumentException("Credit amount cannot be negative: {$value}.");
        }

        return new self($value);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    /** @throws InvalidArgumentException quando o resultado seria negativo. */
    public function subtract(self $other): self
    {
        return self::of($this->value - $other->value);
    }

    public function isZero(): bool
    {
        return $this->value === 0;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function isGreaterThanOrEqualTo(self $other): bool
    {
        return $this->value >= $other->value;
    }

    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }
}

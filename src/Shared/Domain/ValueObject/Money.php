<?php

declare(strict_types=1);

namespace Src\Shared\Domain\ValueObject;

use Src\Shared\Domain\Exception\InvalidArgumentException;

/**
 * Valor monetário representado em centavos (inteiro) + moeda ISO 4217.
 */
final readonly class Money
{
    private function __construct(
        public int $cents,
        public string $currency,
    ) {}

    public static function of(int $cents, string $currency = 'BRL'): self
    {
        if ($cents < 0) {
            throw new InvalidArgumentException("Money cannot be negative: {$cents}.");
        }

        $currency = strtoupper($currency);

        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException("Invalid ISO 4217 currency code: {$currency}.");
        }

        return new self($cents, $currency);
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }

    public function formatted(): string
    {
        return number_format($this->cents / 100, 2, ',', '.');
    }
}

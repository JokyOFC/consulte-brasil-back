<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\ValueObject;

use Src\Shared\Domain\Exception\InvalidArgumentException;
use Stringable;

final readonly class WalletId implements Stringable
{
    public function __construct(public string $value)
    {
        if ($this->value === '') {
            throw new InvalidArgumentException('WalletId cannot be empty.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

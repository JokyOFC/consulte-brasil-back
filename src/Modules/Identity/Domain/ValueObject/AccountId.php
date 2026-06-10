<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Domain\ValueObject;

use Src\Shared\Domain\Exception\InvalidArgumentException;
use Stringable;

final readonly class AccountId implements Stringable
{
    public function __construct(public string $value)
    {
        if ($this->value === '') {
            throw new InvalidArgumentException('AccountId cannot be empty.');
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

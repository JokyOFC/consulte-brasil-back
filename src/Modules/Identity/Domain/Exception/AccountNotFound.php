<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Domain\Exception;

use Src\Modules\Identity\Domain\ValueObject\AccountId;
use Src\Shared\Domain\Exception\DomainException;

final class AccountNotFound extends DomainException
{
    public static function withId(AccountId $id): self
    {
        return new self("Account [{$id->value}] was not found.");
    }
}

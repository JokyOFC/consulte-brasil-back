<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Domain\Exception;

use Src\Shared\Domain\Exception\DomainException;

final class ApiKeyNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("API key [{$id}] was not found for this account.");
    }
}

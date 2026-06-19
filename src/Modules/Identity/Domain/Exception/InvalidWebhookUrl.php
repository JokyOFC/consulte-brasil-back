<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Domain\Exception;

use Src\Shared\Domain\Exception\DomainException;

final class InvalidWebhookUrl extends DomainException
{
    public static function withReason(string $reason): self
    {
        return new self($reason);
    }
}

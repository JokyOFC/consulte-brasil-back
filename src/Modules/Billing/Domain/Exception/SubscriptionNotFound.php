<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Exception;

use Src\Shared\Domain\Exception\DomainException;

final class SubscriptionNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Subscription [{$id}] not found.");
    }
}

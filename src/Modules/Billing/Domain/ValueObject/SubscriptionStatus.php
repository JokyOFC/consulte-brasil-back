<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\ValueObject;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Canceled = 'cancelled';
    case PastDue = 'past_due';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}

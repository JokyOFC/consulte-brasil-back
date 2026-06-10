<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\ValueObject;

enum BillingPeriod: string
{
    case Monthly = 'monthly';
    case OneTime = 'one_time';
}

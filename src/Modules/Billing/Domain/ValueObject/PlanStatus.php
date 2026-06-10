<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\ValueObject;

enum PlanStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}

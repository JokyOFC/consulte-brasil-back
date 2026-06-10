<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Domain\ValueObject;

enum AccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}

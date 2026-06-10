<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Domain\ValueObject;

enum Role: string
{
    case Admin = 'admin';
    case Client = 'client';
}

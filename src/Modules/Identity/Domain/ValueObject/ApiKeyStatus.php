<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Domain\ValueObject;

enum ApiKeyStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}

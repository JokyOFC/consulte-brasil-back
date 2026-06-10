<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Domain\ValueObject;

enum ProviderStatus: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';
}

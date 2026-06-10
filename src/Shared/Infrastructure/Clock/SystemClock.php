<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Clock;

use DateTimeImmutable;
use Src\Shared\Application\Contracts\Clock;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}

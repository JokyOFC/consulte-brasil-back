<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Id;

use Ramsey\Uuid\Uuid;
use Src\Shared\Application\Contracts\IdGenerator;

final class RamseyUuidGenerator implements IdGenerator
{
    public function generate(): string
    {
        return Uuid::uuid4()->toString();
    }
}

<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Infrastructure\Crypto;

use Illuminate\Support\Str;
use Src\Modules\Identity\Application\Port\TokenGenerator;

final class RandomTokenGenerator implements TokenGenerator
{
    public function generateSecret(): string
    {
        // Str::random() usa random_bytes() (CSPRNG).
        return Str::random(40);
    }
}

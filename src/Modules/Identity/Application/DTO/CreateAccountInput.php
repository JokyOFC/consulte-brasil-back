<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Application\DTO;

final readonly class CreateAccountInput
{
    public function __construct(
        public string $name,
        public string $document,
    ) {}
}

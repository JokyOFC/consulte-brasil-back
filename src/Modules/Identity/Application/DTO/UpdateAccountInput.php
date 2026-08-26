<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Application\DTO;

use Src\Modules\Identity\Domain\ValueObject\AccountStatus;

final readonly class UpdateAccountInput
{
    public function __construct(
        public string $accountId,
        public string $name,
        public AccountStatus $status,
    ) {}
}

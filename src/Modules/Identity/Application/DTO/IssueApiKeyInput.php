<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Application\DTO;

use DateTimeImmutable;

final readonly class IssueApiKeyInput
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $accountId,
        public string $name,
        public array $scopes = [],
        public ?DateTimeImmutable $expiresAt = null,
    ) {}
}

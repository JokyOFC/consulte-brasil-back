<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\DTO;

final readonly class ReserveCreditsInput
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $accountId,
        public int $amount,
        public ?string $referenceType = null,
        public ?string $referenceId = null,
        public ?string $idempotencyKey = null,
        public array $metadata = [],
    ) {}
}

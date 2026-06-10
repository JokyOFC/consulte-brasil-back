<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\DTO;

final readonly class CreatePlanInput
{
    /** @param array<string, mixed> $features */
    public function __construct(
        public string $name,
        public string $slug,
        public int $priceCents,
        public int $includedCredits,
        public string $billingPeriod = 'monthly',
        public ?int $overagePriceCents = null,
        public array $features = [],
        public string $currency = 'BRL',
    ) {}
}

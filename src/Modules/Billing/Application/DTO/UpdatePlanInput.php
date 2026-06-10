<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\DTO;

final readonly class UpdatePlanInput
{
    public function __construct(
        public string $planId,
        public string $name,
        public int $priceCents,
        public int $includedCredits,
        public string $billingPeriod,
        public ?int $overagePriceCents,
        public string $status,
    ) {}
}

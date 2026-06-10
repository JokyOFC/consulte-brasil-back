<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Entity;

use Src\Modules\Billing\Domain\ValueObject\BillingPeriod;
use Src\Modules\Billing\Domain\ValueObject\PlanStatus;
use Src\Shared\Domain\ValueObject\Money;

final class Plan
{
    /** @param array<string, mixed> $features */
    public function __construct(
        public readonly string $id,
        public string $name,
        public readonly string $slug,
        public Money $price,
        public BillingPeriod $billingPeriod,
        public int $includedCredits,
        public ?Money $overagePrice,
        public array $features,
        public PlanStatus $status,
    ) {}

    public function archive(): void
    {
        $this->status = PlanStatus::Archived;
    }

    public function isActive(): bool
    {
        return $this->status === PlanStatus::Active;
    }
}

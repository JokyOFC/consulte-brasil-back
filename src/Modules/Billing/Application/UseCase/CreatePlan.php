<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Src\Modules\Billing\Application\DTO\CreatePlanInput;
use Src\Modules\Billing\Domain\Entity\Plan;
use Src\Modules\Billing\Domain\Repository\PlanRepository;
use Src\Modules\Billing\Domain\ValueObject\BillingPeriod;
use Src\Modules\Billing\Domain\ValueObject\PlanStatus;
use Src\Shared\Application\Contracts\IdGenerator;
use Src\Shared\Domain\ValueObject\Money;

final readonly class CreatePlan
{
    public function __construct(
        private PlanRepository $plans,
        private IdGenerator $ids,
    ) {}

    public function handle(CreatePlanInput $input): Plan
    {
        $plan = new Plan(
            id: $this->ids->generate(),
            name: $input->name,
            slug: $input->slug,
            price: Money::of($input->priceCents, $input->currency),
            billingPeriod: BillingPeriod::from($input->billingPeriod),
            includedCredits: $input->includedCredits,
            overagePrice: $input->overagePriceCents !== null
                ? Money::of($input->overagePriceCents, $input->currency)
                : null,
            features: $input->features,
            status: PlanStatus::Active,
        );

        $this->plans->save($plan);

        return $plan;
    }
}

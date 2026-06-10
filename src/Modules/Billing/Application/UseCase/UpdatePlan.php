<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Src\Modules\Billing\Application\DTO\UpdatePlanInput;
use Src\Modules\Billing\Domain\Entity\Plan;
use Src\Modules\Billing\Domain\Repository\PlanRepository;
use Src\Modules\Billing\Domain\ValueObject\BillingPeriod;
use Src\Modules\Billing\Domain\ValueObject\PlanStatus;
use Src\Shared\Domain\ValueObject\Money;

final readonly class UpdatePlan
{
    public function __construct(
        private PlanRepository $plans,
    ) {}

    public function handle(UpdatePlanInput $input): Plan
    {
        $plan = $this->plans->findById($input->planId);
        if ($plan === null) {
            throw new \InvalidArgumentException('Plano não encontrado.');
        }

        $plan->name = $input->name;
        $plan->price = Money::of($input->priceCents, $plan->price->currency);
        $plan->billingPeriod = BillingPeriod::from($input->billingPeriod);
        $plan->includedCredits = $input->includedCredits;
        $plan->overagePrice = $input->overagePriceCents !== null
            ? Money::of($input->overagePriceCents, $plan->price->currency)
            : null;
        $plan->status = PlanStatus::from($input->status);

        $this->plans->save($plan);

        return $plan;
    }
}

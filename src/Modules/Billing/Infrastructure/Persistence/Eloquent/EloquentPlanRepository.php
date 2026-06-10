<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Persistence\Eloquent;

use Src\Modules\Billing\Domain\Entity\Plan;
use Src\Modules\Billing\Domain\Repository\PlanRepository;
use Src\Modules\Billing\Domain\ValueObject\BillingPeriod;
use Src\Modules\Billing\Domain\ValueObject\PlanStatus;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use Src\Shared\Domain\ValueObject\Money;

final class EloquentPlanRepository implements PlanRepository
{
    public function save(Plan $plan): void
    {
        $model = PlanModel::find($plan->id) ?? new PlanModel;

        $model->id = $plan->id;
        $model->name = $plan->name;
        $model->slug = $plan->slug;
        $model->price_cents = $plan->price->cents;
        $model->currency = $plan->price->currency;
        $model->billing_period = $plan->billingPeriod->value;
        $model->included_credits = $plan->includedCredits;
        $model->overage_price_cents = $plan->overagePrice?->cents;
        $model->features = $plan->features;
        $model->status = $plan->status->value;
        $model->save();
    }

    public function findById(string $id): ?Plan
    {
        $model = PlanModel::find($id);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function findBySlug(string $slug): ?Plan
    {
        $model = PlanModel::query()->where('slug', $slug)->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function active(): array
    {
        return PlanModel::query()
            ->where('status', PlanStatus::Active->value)
            ->orderBy('price_cents')
            ->get()
            ->map(fn (PlanModel $m): Plan => $this->toEntity($m))
            ->all();
    }

    private function toEntity(PlanModel $model): Plan
    {
        return new Plan(
            id: $model->id,
            name: $model->name,
            slug: $model->slug,
            price: Money::of($model->price_cents, $model->currency),
            billingPeriod: BillingPeriod::from($model->billing_period),
            includedCredits: $model->included_credits,
            overagePrice: $model->overage_price_cents !== null
                ? Money::of($model->overage_price_cents, $model->currency)
                : null,
            features: $model->features ?? [],
            status: PlanStatus::from($model->status),
        );
    }
}

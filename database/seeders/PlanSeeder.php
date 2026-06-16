<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Modules\Billing\Application\DTO\CreatePlanInput;
use Src\Modules\Billing\Application\UseCase\CreatePlan;
use Src\Modules\Billing\Domain\Repository\PlanRepository;

final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            ['Starter', 'starter', 4900, 10000, 'monthly'],
            ['Growth', 'growth', 14900, 50000, 'monthly'],
            ['Scale', 'scale', 49900, 200000, 'monthly'],
        ];

        $plans = app(PlanRepository::class);
        $create = app(CreatePlan::class);

        foreach ($catalog as [$name, $slug, $priceCents, $credits, $period]) {
            if ($plans->findBySlug($slug) !== null) {
                continue;
            }
            $create->handle(new CreatePlanInput(
                name: $name,
                slug: $slug,
                priceCents: $priceCents,
                includedCredits: $credits,
                billingPeriod: $period,
            ));
        }
    }
}

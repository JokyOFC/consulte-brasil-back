<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Modules\Billing\Application\DTO\CreatePlanInput;
use Src\Modules\Billing\Application\UseCase\CreatePlan;
use Src\Modules\Billing\Application\UseCase\UpdatePlan;
use Src\Modules\Billing\Application\DTO\UpdatePlanInput;
use Tests\TestCase;

final class PlansApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_plans_endpoint_returns_active_plans_without_auth(): void
    {
        $growth = app(CreatePlan::class)->handle(new CreatePlanInput(
            name: 'Growth',
            slug: 'growth',
            priceCents: 14900,
            includedCredits: 50000,
        ));

        app(CreatePlan::class)->handle(new CreatePlanInput(
            name: 'Scale',
            slug: 'scale',
            priceCents: 49900,
            includedCredits: 200000,
        ));

        $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'growth')
            ->assertJsonPath('data.0.price_cents', 14900)
            ->assertJsonPath('data.0.included_balance_cents', 50000)
            ->assertJsonPath('data.0.price_formatted', 'R$ 149,00')
            ->assertJsonPath('data.0.included_balance_formatted', 'R$ 500,00')
            ->assertJsonPath('data.0.billing_period', 'monthly')
            ->assertJsonPath('data.0.id', $growth->id);
    }

    public function test_plans_endpoint_excludes_archived_plans(): void
    {
        app(CreatePlan::class)->handle(new CreatePlanInput(
            name: 'Growth',
            slug: 'growth',
            priceCents: 14900,
            includedCredits: 50000,
        ));

        $archived = app(CreatePlan::class)->handle(new CreatePlanInput(
            name: 'Legacy',
            slug: 'legacy',
            priceCents: 9900,
            includedCredits: 10000,
        ));

        app(UpdatePlan::class)->handle(new UpdatePlanInput(
            planId: $archived->id,
            name: 'Legacy',
            priceCents: 9900,
            includedCredits: 10000,
            billingPeriod: 'monthly',
            overagePriceCents: null,
            status: 'archived',
        ));

        $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'growth');
    }

    public function test_plan_show_returns_plan_by_slug(): void
    {
        app(CreatePlan::class)->handle(new CreatePlanInput(
            name: 'Growth',
            slug: 'growth',
            priceCents: 14900,
            includedCredits: 50000,
        ));

        $this->getJson('/api/v1/plans/growth')
            ->assertOk()
            ->assertJsonPath('data.slug', 'growth')
            ->assertJsonPath('data.name', 'Growth');
    }

    public function test_plan_show_returns_404_for_missing_or_archived_plan(): void
    {
        $this->getJson('/api/v1/plans/inexistente')
            ->assertNotFound()
            ->assertJsonPath('error.type', 'plan_not_found');

        $archived = app(CreatePlan::class)->handle(new CreatePlanInput(
            name: 'Legacy',
            slug: 'legacy',
            priceCents: 9900,
            includedCredits: 10000,
        ));

        app(UpdatePlan::class)->handle(new UpdatePlanInput(
            planId: $archived->id,
            name: 'Legacy',
            priceCents: 9900,
            includedCredits: 10000,
            billingPeriod: 'monthly',
            overagePriceCents: null,
            status: 'archived',
        ));

        $this->getJson('/api/v1/plans/legacy')
            ->assertNotFound()
            ->assertJsonPath('error.type', 'plan_not_found');
    }
}

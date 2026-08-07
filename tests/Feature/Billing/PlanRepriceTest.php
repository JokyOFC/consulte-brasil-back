<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Modules\Billing\Application\DTO\SubscribeToPlanInput;
use Src\Modules\Billing\Application\DTO\UpdatePlanInput;
use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Application\UseCase\RunRecurringBilling;
use Src\Modules\Billing\Application\UseCase\SubscribeToPlan;
use Src\Modules\Billing\Application\UseCase\UpdatePlan;
use Src\Modules\Billing\Domain\ValueObject\PaymentMethod;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Shared\Application\Contracts\IdGenerator;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\TestCase;

/**
 * Grandfathering de preço: o preço é congelado na assinatura; mudar o preço
 * do plano só afeta assinantes existentes quando o admin opta por aplicar.
 */
final class PlanRepriceTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function seedAccount(string $cnpj = '11.222.333/0001-81'): string
    {
        return app(CreateAccount::class)->handle(new CreateAccountInput('ACME', $cnpj))->id->value;
    }

    private function seedPlan(int $priceCents = 9900): string
    {
        $id = app(IdGenerator::class)->generate();
        PlanModel::query()->create([
            'id' => $id,
            'name' => 'Plano Pro',
            'slug' => 'pro-'.substr($id, 0, 8),
            'price_cents' => $priceCents,
            'currency' => 'BRL',
            'billing_period' => 'monthly',
            'included_credits' => 10000,
            'overage_price_cents' => null,
            'features' => [],
            'status' => 'active',
        ]);

        return $id;
    }

    private function subscribeManual(string $accountId, string $planId): string
    {
        $result = app(SubscribeToPlan::class)->handle(new SubscribeToPlanInput(
            accountId: $accountId,
            planId: $planId,
            method: PaymentMethod::Pix,
            payerEmail: 'a@b.com',
            backUrl: 'https://app.test/billing',
        ));

        return $result['subscription']->id;
    }

    private function updatePlanPrice(string $planId, int $newPriceCents, bool $applyToExisting)
    {
        return app(UpdatePlan::class)->handle(new UpdatePlanInput(
            planId: $planId,
            name: 'Plano Pro',
            priceCents: $newPriceCents,
            includedCredits: 10000,
            billingPeriod: 'monthly',
            overagePriceCents: null,
            status: 'active',
            applyToExistingSubscribers: $applyToExisting,
        ));
    }

    public function test_subscription_snapshots_plan_price_at_subscribe(): void
    {
        $planId = $this->seedPlan(9900);
        $subscriptionId = $this->subscribeManual($this->seedAccount(), $planId);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscriptionId,
            'price_cents' => 9900,
            'currency' => 'BRL',
        ]);
    }

    public function test_price_change_does_not_affect_existing_subscribers_by_default(): void
    {
        $planId = $this->seedPlan(9900);
        $subscriptionId = $this->subscribeManual($this->seedAccount(), $planId);

        $result = $this->updatePlanPrice($planId, 14900, applyToExisting: false);

        $this->assertSame(0, $result->repricedSubscriptions);
        $this->assertDatabaseHas('plans', ['id' => $planId, 'price_cents' => 14900]);
        $this->assertDatabaseHas('subscriptions', ['id' => $subscriptionId, 'price_cents' => 9900]);

        // Renovação: quita a 1ª fatura e força o fim do ciclo.
        DB::table('invoices')->where('subscription_id', $subscriptionId)->update(['status' => 'paid']);
        DB::table('subscriptions')->where('id', $subscriptionId)->update(['next_billing_at' => now()->subDay()]);

        app(RunRecurringBilling::class)->handle();

        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscriptionId,
            'status' => 'open',
            'amount_cents' => 9900,
        ]);
    }

    public function test_new_subscriber_pays_the_new_price(): void
    {
        $planId = $this->seedPlan(9900);
        $this->subscribeManual($this->seedAccount(), $planId);

        $this->updatePlanPrice($planId, 14900, applyToExisting: false);

        $newSubscriptionId = $this->subscribeManual($this->seedAccount('04.252.011/0001-10'), $planId);

        $this->assertDatabaseHas('subscriptions', ['id' => $newSubscriptionId, 'price_cents' => 14900]);
        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $newSubscriptionId,
            'amount_cents' => 14900,
        ]);
    }

    public function test_price_change_can_be_applied_to_existing_subscribers(): void
    {
        $planId = $this->seedPlan(9900);
        $subscriptionId = $this->subscribeManual($this->seedAccount(), $planId);

        $result = $this->updatePlanPrice($planId, 14900, applyToExisting: true);

        $this->assertSame(1, $result->repricedSubscriptions);
        $this->assertSame(0, $result->preapprovalUpdateFailures);
        $this->assertDatabaseHas('subscriptions', ['id' => $subscriptionId, 'price_cents' => 14900]);

        DB::table('invoices')->where('subscription_id', $subscriptionId)->update(['status' => 'paid']);
        DB::table('subscriptions')->where('id', $subscriptionId)->update(['next_billing_at' => now()->subDay()]);

        app(RunRecurringBilling::class)->handle();

        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscriptionId,
            'status' => 'open',
            'amount_cents' => 14900,
        ]);
    }

    public function test_reprice_does_not_touch_cancelled_subscriptions(): void
    {
        $planId = $this->seedPlan(9900);
        $subscriptionId = $this->subscribeManual($this->seedAccount(), $planId);
        DB::table('subscriptions')->where('id', $subscriptionId)->update(['status' => 'canceled']);

        $result = $this->updatePlanPrice($planId, 14900, applyToExisting: true);

        $this->assertSame(0, $result->repricedSubscriptions);
        $this->assertDatabaseHas('subscriptions', ['id' => $subscriptionId, 'price_cents' => 9900]);
    }

    public function test_reprice_updates_card_preapproval_amount_in_gateway(): void
    {
        $planId = $this->seedPlan(9900);
        $result = app(SubscribeToPlan::class)->handle(new SubscribeToPlanInput(
            accountId: $this->seedAccount(),
            planId: $planId,
            method: PaymentMethod::CreditCard,
            payerEmail: 'a@b.com',
            backUrl: 'https://app.test/billing',
            cardToken: 'tok_123',
        ));
        $subscription = $result['subscription'];
        $this->assertNotNull($subscription->mpPreapprovalId);

        $updateResult = $this->updatePlanPrice($planId, 14900, applyToExisting: true);

        $this->assertSame(1, $updateResult->repricedSubscriptions);
        $this->assertSame(
            [['id' => $subscription->mpPreapprovalId, 'amount_cents' => 14900]],
            $this->gateway->updatedPreapprovals,
        );
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'price_cents' => 14900]);
    }

    public function test_reprice_keeps_old_price_when_preapproval_update_fails(): void
    {
        $planId = $this->seedPlan(9900);
        $result = app(SubscribeToPlan::class)->handle(new SubscribeToPlanInput(
            accountId: $this->seedAccount(),
            planId: $planId,
            method: PaymentMethod::CreditCard,
            payerEmail: 'a@b.com',
            backUrl: 'https://app.test/billing',
            cardToken: 'tok_123',
        ));

        $this->gateway->failPreapprovalUpdates = true;

        $updateResult = $this->updatePlanPrice($planId, 14900, applyToExisting: true);

        $this->assertSame(0, $updateResult->repricedSubscriptions);
        $this->assertSame(1, $updateResult->preapprovalUpdateFailures);
        // Fatura precisa bater com o que o MP cobra de fato → mantém o antigo.
        $this->assertDatabaseHas('subscriptions', [
            'id' => $result['subscription']->id,
            'price_cents' => 9900,
        ]);
        $this->assertDatabaseHas('plans', ['id' => $planId, 'price_cents' => 14900]);
    }

    public function test_admin_endpoint_accepts_apply_flag(): void
    {
        $planId = $this->seedPlan(9900);
        $subscriptionId = $this->subscribeManual($this->seedAccount(), $planId);
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->from("/admin/plans/{$planId}")
            ->put("/admin/plans/{$planId}", [
                'name' => 'Plano Pro',
                'price_cents' => 14900,
                'included_credits' => 10000,
                'billing_period' => 'monthly',
                'overage_price_cents' => null,
                'status' => 'active',
                'apply_to_existing_subscribers' => true,
            ])
            ->assertRedirect("/admin/plans/{$planId}")
            ->assertSessionHas('success');

        $this->assertDatabaseHas('subscriptions', ['id' => $subscriptionId, 'price_cents' => 14900]);
    }
}

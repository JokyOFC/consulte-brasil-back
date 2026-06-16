<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\QueryTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Modules\Billing\Application\DTO\CreatePlanInput;
use Src\Modules\Billing\Application\UseCase\CreatePlan;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Modules\Provider\Domain\Entity\Provider;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;
use Src\Modules\Provider\Domain\ValueObject\ProviderEnvironment;
use Src\Modules\Provider\Domain\ValueObject\ProviderStatus;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;
use Src\Shared\Application\Contracts\IdGenerator;
use Tests\TestCase;

final class AdminFlowsTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_dashboard_requires_admin_role(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_view_and_update_plan(): void
    {
        $admin = $this->adminUser();
        $plan = app(CreatePlan::class)->handle(new CreatePlanInput(
            name: 'Growth',
            slug: 'growth',
            priceCents: 14900,
            includedCredits: 500,
        ));

        $this->actingAs($admin)
            ->get("/admin/plans/{$plan->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/plans/show')
                ->has('plan', fn ($planPage) => $planPage
                    ->where('name', 'Growth')
                    ->where('slug', 'growth')
                    ->where('price_cents', 14900)
                    ->etc()
                )
                ->has('stats')
            );

        $this->actingAs($admin)
            ->from("/admin/plans/{$plan->id}")
            ->put("/admin/plans/{$plan->id}", [
                'name' => 'Growth Plus',
                'price_cents' => 19900,
                'included_credits' => 750,
                'billing_period' => 'monthly',
                'overage_price_cents' => null,
                'status' => 'active',
            ])
            ->assertRedirect("/admin/plans/{$plan->id}")
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => 'Growth Plus',
            'price_cents' => 19900,
            'included_credits' => 750,
        ]);
    }

    public function test_admin_can_create_plan_via_web(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post('/admin/plans', [
                'name' => 'Starter',
                'slug' => 'starter',
                'price_cents' => 4900,
                'included_credits' => 100,
                'billing_period' => 'monthly',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plans', ['slug' => 'starter', 'included_credits' => 100]);
    }

    public function test_creating_account_with_invalid_document_returns_validation_error(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->from('/admin/accounts')
            ->post('/admin/accounts', ['name' => 'Cliente X', 'document' => '214123423143'])
            ->assertRedirect('/admin/accounts')
            ->assertSessionHasErrors('document');

        $this->assertDatabaseMissing('accounts', ['name' => 'Cliente X']);
    }

    public function test_creating_account_with_duplicate_document_is_rejected(): void
    {
        $admin = $this->adminUser();
        app(CreateAccount::class)->handle(new CreateAccountInput('ACME', '11.222.333/0001-81'));

        $this->actingAs($admin)
            ->from('/admin/accounts')
            ->post('/admin/accounts', ['name' => 'Outra', 'document' => '11.222.333/0001-81'])
            ->assertRedirect('/admin/accounts')
            ->assertSessionHasErrors('document');
    }

    public function test_creating_plan_with_duplicate_slug_is_rejected(): void
    {
        $admin = $this->adminUser();
        app(CreatePlan::class)->handle(new CreatePlanInput(
            name: 'Pro',
            slug: 'pro',
            priceCents: 9900,
            includedCredits: 500,
        ));

        $this->actingAs($admin)
            ->from('/admin/plans')
            ->post('/admin/plans', [
                'name' => 'Pro 2',
                'slug' => 'pro',
                'price_cents' => 1000,
                'included_credits' => 10,
                'billing_period' => 'monthly',
            ])
            ->assertRedirect('/admin/plans')
            ->assertSessionHasErrors('slug');
    }

    public function test_admin_can_view_account_detail_page(): void
    {
        $admin = $this->adminUser();
        $account = app(CreateAccount::class)->handle(new CreateAccountInput('ACME Ltda', '11.222.333/0001-81'));

        $this->actingAs($admin)
            ->get("/admin/accounts/{$account->id->value}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/accounts/show')
                ->has('account', fn ($accountPage) => $accountPage
                    ->where('name', 'ACME Ltda')
                    ->where('document', '11222333000181')
                    ->etc()
                )
                ->has('wallet')
                ->has('stats')
            );
    }

    public function test_assigning_plan_to_account_grants_credits(): void
    {
        $admin = $this->adminUser();
        $account = app(CreateAccount::class)->handle(new CreateAccountInput('ACME', '11.222.333/0001-81'));
        $plan = app(CreatePlan::class)->handle(new CreatePlanInput(
            name: 'Pro',
            slug: 'pro',
            priceCents: 9900,
            includedCredits: 500,
        ));

        $this->actingAs($admin)
            ->withConfirmedPassword()
            ->post("/admin/accounts/{$account->id->value}/assign-plan", ['plan_id' => $plan->id])
            ->assertRedirect();

        $wallet = app(WalletRepository::class)->findByAccountId($account->id->value);
        $this->assertSame(500, $wallet->balance()->value);

        $this->assertDatabaseHas('subscriptions', [
            'account_id' => $account->id->value,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_configure_capability_endpoint_and_device_token(): void
    {
        $admin = $this->adminUser();
        $providers = app(ProviderRepository::class);
        $ids = app(IdGenerator::class);

        $providers->save(new Provider(
            id: $ids->generate(),
            identifier: 'api_brasil',
            name: 'API Brasil',
            status: ProviderStatus::Enabled,
            baseUrl: 'https://gateway.apibrasil.io/api/v2',
            credentials: ['token' => 'bearer'],
        ));
        $providerId = ProviderModel::query()->where('identifier', 'api_brasil')->value('id');

        $this->actingAs($admin)
            ->withConfirmedPassword()
            ->post("/admin/providers/{$providerId}/capabilities", [
                'query_type' => 'cpf',
                'priority' => 1,
                'price_cents' => 1,
                'enabled' => true,
                'endpoint' => '/dados/cpf',
                'body_key' => 'cpf',
                'device_token' => 'device-secret-123',
            ])
            ->assertRedirect();

        // Endpoint/body_key ficam no config (sem barra inicial).
        $this->assertSame(
            ['endpoint' => 'dados/cpf', 'body_key' => 'cpf'],
            $providers->capabilityConfig('api_brasil', 'cpf'),
        );

        // Device token é guardado cifrado nas credenciais do provider.
        $entity = $providers->findByIdentifier('api_brasil');
        $this->assertSame('device-secret-123', $entity->credentials['device_tokens']['cpf'] ?? null);
    }

    public function test_admin_can_switch_provider_environment(): void
    {
        $admin = $this->adminUser();
        $providers = app(ProviderRepository::class);
        $ids = app(IdGenerator::class);

        $providers->save(new Provider(
            id: $ids->generate(),
            identifier: 'cpfcnpj',
            name: 'CPF.CNPJ',
            status: ProviderStatus::Enabled,
            baseUrl: 'https://api.cpfcnpj.com.br',
            credentials: ['token' => 'prod', 'sandbox_token' => 'sbx'],
            environment: ProviderEnvironment::Production,
        ));
        $providerId = ProviderModel::query()->where('identifier', 'cpfcnpj')->value('id');

        $this->actingAs($admin)
            ->post("/admin/providers/{$providerId}/environment", ['environment' => 'sandbox'])
            ->assertRedirect();

        $this->assertSame(
            ProviderEnvironment::Sandbox,
            $providers->findByIdentifier('cpfcnpj')->environment,
        );
        $this->assertDatabaseHas('providers', ['id' => $providerId, 'environment' => 'sandbox']);
    }

    public function test_admin_adjust_credits_logs_in_ledger(): void
    {
        $admin = $this->adminUser();
        $account = app(CreateAccount::class)->handle(new CreateAccountInput('ACME', '11.222.333/0001-81'));

        $this->actingAs($admin)
            ->withConfirmedPassword()
            ->post("/admin/accounts/{$account->id->value}/adjust", [
                'delta' => 250,
                'reason' => 'compensação manual',
            ])
            ->assertRedirect();

        $this->assertSame(250, app(WalletRepository::class)->findByAccountId($account->id->value)->balance()->value);
        $this->assertDatabaseHas('credit_transactions', [
            'account_id' => $account->id->value,
            'type' => 'adjustment',
            'direction' => 'credit',
            'amount' => 250,
        ]);
    }

    public function test_admin_can_view_and_update_query_type_cache_ttl(): void
    {
        $admin = $this->adminUser();
        $this->seed(QueryTypeSeeder::class);

        $queryTypeId = (string) DB::table('query_types')->where('code', 'cpf')->value('id');

        $this->actingAs($admin)
            ->get('/admin/query-types')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/query-types/index')
                ->has('query_types', 2)
                ->where('query_types', fn ($types) => collect($types)->contains(fn ($row) => $row['code'] === 'cpf'))
            );

        $this->actingAs($admin)
            ->from('/admin/query-types')
            ->put("/admin/query-types/{$queryTypeId}", [
                'cache_ttl_seconds' => 3600,
            ])
            ->assertRedirect('/admin/query-types')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('query_types', [
            'id' => $queryTypeId,
            'cache_ttl_seconds' => 3600,
        ]);
    }
}

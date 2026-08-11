<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Pricing;
use Database\Seeders\CpfCnpjCatalogSeeder;
use Database\Seeders\ProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Src\Modules\Provider\Domain\Entity\Provider;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;
use Src\Modules\Provider\Domain\ValueObject\ProviderEnvironment;
use Src\Modules\Provider\Domain\ValueObject\ProviderStatus;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderCapabilityModel;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;
use Src\Shared\Application\Contracts\IdGenerator;
use Tests\TestCase;

final class ProviderDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_capabilities_apply_markup_over_cost(): void
    {
        $this->seed(ProviderSeeder::class);
        $this->seed(CpfCnpjCatalogSeeder::class);

        // Legado api_brasil: custo 29 → venda = custo + 10%.
        $apiBrasilId = ProviderModel::query()->where('identifier', 'api_brasil')->value('id');
        $cpf = ProviderCapabilityModel::query()
            ->where('provider_id', $apiBrasilId)
            ->where('query_type', 'cpf')
            ->firstOrFail();

        $this->assertSame(29, $cpf->cost_cents);
        $this->assertSame(Pricing::sellPriceCents(29), $cpf->price_cents);
        $this->assertSame(32, $cpf->price_cents);

        // Catálogo CPF.CNPJ: custo 17 (cpf_nome) → venda = 19.
        $cpfcnpjId = ProviderModel::query()->where('identifier', 'cpfcnpj')->value('id');
        $cpfNome = ProviderCapabilityModel::query()
            ->where('provider_id', $cpfcnpjId)
            ->where('query_type', 'cpf_nome')
            ->firstOrFail();

        $this->assertSame(17, $cpfNome->cost_cents);
        $this->assertSame(Pricing::sellPriceCents(17), $cpfNome->price_cents);
    }

    public function test_provider_detail_dashboard_renders_for_admin(): void
    {
        $this->seed(ProviderSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $providerId = ProviderModel::query()->where('identifier', 'api_brasil')->value('id');

        $this->actingAs($admin)
            ->get("/admin/providers/{$providerId}")
            ->assertOk();
    }

    public function test_cpfcnpj_balance_enumerates_packages_from_enabled_capabilities(): void
    {
        $this->configureCpfCnpj();
        $this->seed(ProviderSeeder::class);

        $providerId = (string) ProviderModel::query()->where('identifier', 'cpfcnpj')->value('id');

        // Pacote extra habilitado deve entrar; desabilitado e endpoint não
        // numérico devem ficar de fora.
        $this->createCapability($providerId, 'cpf_cac', '27', enabled: true);
        $this->createCapability($providerId, 'cpf_completo', '9', enabled: false);
        $this->createCapability($providerId, 'cpf_nome', '', enabled: true);

        Http::fake([
            'api.cpfcnpj.test/test-token/saldo/2' => Http::response($this->saldoBody(2, 'CPF Básico', 100)),
            'api.cpfcnpj.test/test-token/saldo/6' => Http::response($this->saldoBody(6, 'CNPJ Completo', 200)),
            'api.cpfcnpj.test/test-token/saldo/27' => Http::response($this->saldoBody(27, 'CAC/SINIC', 300)),
            '*' => Http::response([], 404),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->getJson("/admin/providers/{$providerId}/balance")
            ->assertOk();

        $balance = $response->json('balance');

        $this->assertSame('ok', $balance['status']);
        $this->assertSame(['2', '6', '27'], array_column($balance['packages'], 'id'));
        $this->assertSame(600, $balance['total']);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/saldo/9'));
    }

    public function test_cpfcnpj_balance_falls_back_to_config_packages_without_capabilities(): void
    {
        $this->configureCpfCnpj();

        app(ProviderRepository::class)->save(new Provider(
            id: app(IdGenerator::class)->generate(),
            identifier: 'cpfcnpj',
            name: 'CPF.CNPJ',
            status: ProviderStatus::Enabled,
            baseUrl: 'https://api.cpfcnpj.test',
            credentials: ['sandbox_token' => 'test-token'],
            environment: ProviderEnvironment::Sandbox,
        ));

        $providerId = (string) ProviderModel::query()->where('identifier', 'cpfcnpj')->value('id');

        Http::fake([
            'api.cpfcnpj.test/test-token/saldo/2' => Http::response($this->saldoBody(2, 'CPF Básico', 50)),
            'api.cpfcnpj.test/test-token/saldo/6' => Http::response($this->saldoBody(6, 'CNPJ Completo', 70)),
            '*' => Http::response([], 404),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->getJson("/admin/providers/{$providerId}/balance")
            ->assertOk();

        $balance = $response->json('balance');

        $this->assertSame('ok', $balance['status']);
        $this->assertSame(['2', '6'], array_column($balance['packages'], 'id'));
        $this->assertSame(120, $balance['total']);
    }

    private function configureCpfCnpj(): void
    {
        config()->set('services.cpfcnpj', [
            'base_url' => 'https://api.cpfcnpj.test',
            'token' => '',
            'sandbox_token' => 'test-token',
            'timeout' => 5,
            'packages' => ['cpf' => '2', 'cnpj' => '6'],
        ]);
    }

    private function createCapability(string $providerId, string $queryType, string $endpoint, bool $enabled): void
    {
        $model = new ProviderCapabilityModel;
        $model->id = app(IdGenerator::class)->generate();
        $model->provider_id = $providerId;
        $model->query_type = $queryType;
        $model->priority = 10;
        $model->price_cents = 30;
        $model->cost_cents = 27;
        $model->enabled = $enabled;
        $model->config = $endpoint !== '' ? ['endpoint' => $endpoint] : null;
        $model->save();
    }

    /** @return array{pacote: array{id: int, nome: string, saldo: int}} */
    private function saldoBody(int $id, string $nome, int $saldo): array
    {
        return ['pacote' => ['id' => $id, 'nome' => $nome, 'saldo' => $saldo]];
    }

    public function test_provider_detail_dashboard_blocks_non_admin(): void
    {
        $this->seed(ProviderSeeder::class);
        $client = User::factory()->create(['role' => 'client']);

        $providerId = ProviderModel::query()->where('identifier', 'api_brasil')->value('id');

        $this->actingAs($client)
            ->get("/admin/providers/{$providerId}")
            ->assertForbidden();
    }
}

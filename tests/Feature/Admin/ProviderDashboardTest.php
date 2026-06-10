<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Pricing;
use Database\Seeders\CpfCnpjCatalogSeeder;
use Database\Seeders\ProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderCapabilityModel;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;
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

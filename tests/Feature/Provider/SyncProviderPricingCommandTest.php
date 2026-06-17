<?php

declare(strict_types=1);

namespace Tests\Feature\Provider;

use App\Support\Pricing;
use Database\Seeders\ApiBrasilCatalogSeeder;
use Database\Seeders\CpfCnpjCatalogSeeder;
use Database\Seeders\ProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderCapabilityModel;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;
use Tests\TestCase;

final class SyncProviderPricingCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProviderSeeder::class);
        $this->seed(ApiBrasilCatalogSeeder::class);
        $this->seed(CpfCnpjCatalogSeeder::class);
    }

    public function test_dry_run_does_not_change_prices(): void
    {
        $this->setOutdatedPrice('cpf_analise_credito_basic', 120, 132);

        $this->artisan('catalog:reprice', ['--dry-run' => true])
            ->expectsOutputToContain('cpf_analise_credito_basic')
            ->assertSuccessful();

        $this->assertCapabilityPrice('cpf_analise_credito_basic', 120, 132);
    }

    public function test_reprice_updates_outdated_catalog_prices(): void
    {
        $expectedCost = ApiBrasilCatalogSeeder::costMap()['cpf_analise_credito_basic'];
        $expectedPrice = Pricing::sellPriceCents($expectedCost);

        $this->setOutdatedPrice('cpf_analise_credito_basic', 120, 132);
        $this->setOutdatedPrice('cpf_cns', 24, 26);

        $this->artisan('catalog:reprice', ['--force' => true])
            ->expectsOutputToContain('Reprecificação concluída')
            ->assertSuccessful();

        $this->assertCapabilityPrice('cpf_analise_credito_basic', $expectedCost, $expectedPrice);
        $this->assertSame(
            $expectedPrice,
            (int) DB::table('query_types')->where('code', 'cpf_analise_credito_basic')->value('default_credit_cost'),
        );

        $cnsCost = CpfCnpjCatalogSeeder::costMap()['cpf_cns'];
        $cnsPrice = Pricing::sellPriceCents($cnsCost);
        $this->assertCapabilityPrice('cpf_cns', $cnsCost, $cnsPrice);
    }

    public function test_reprice_is_idempotent_when_prices_are_current(): void
    {
        $this->artisan('catalog:reprice', ['--force' => true])
            ->expectsOutputToContain('Nenhuma alteração necessária')
            ->assertSuccessful();
    }

    private function setOutdatedPrice(string $queryType, int $costCents, int $priceCents): void
    {
        ProviderCapabilityModel::query()
            ->where('query_type', $queryType)
            ->update(['cost_cents' => $costCents, 'price_cents' => $priceCents]);

        DB::table('query_types')
            ->where('code', $queryType)
            ->update(['default_credit_cost' => $priceCents]);
    }

    private function assertCapabilityPrice(string $queryType, int $costCents, int $priceCents): void
    {
        $capability = ProviderCapabilityModel::query()
            ->where('query_type', $queryType)
            ->firstOrFail();

        $this->assertSame($costCents, (int) $capability->cost_cents);
        $this->assertSame($priceCents, (int) $capability->price_cents);
    }
}

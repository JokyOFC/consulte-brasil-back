<?php

declare(strict_types=1);

namespace Tests\Feature\Consultation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ServicesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_endpoint_returns_active_catalog_without_auth(): void
    {
        $this->seedCatalog();

        $response = $this->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $data = $response->json('data');
        $codes = array_column($data, 'code');
        $this->assertContains('cpf', $codes);
        $this->assertContains('ab_veiculos_dados', $codes);

        $cpf = collect($data)->firstWhere('code', 'cpf');
        $this->assertSame('Dados cadastrais de PF', $cpf['name']);
        $this->assertSame('Pessoa Física (CPF)', $cpf['group']);
        $this->assertSame(29, $cpf['price_cents']);
        $this->assertSame('R$ 0,29', $cpf['price_formatted']);

        $response
            ->assertJsonCount(2, 'groups')
            ->assertJsonPath('groups.0.name', 'Pessoa Física (CPF)')
            ->assertJsonPath('groups.0.services.0.code', 'cpf')
            ->assertJsonPath('groups.1.name', 'Veículos');
    }

    public function test_services_endpoint_excludes_disabled_and_internal_routes(): void
    {
        $this->seedCatalog();

        $this->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonMissing(['code' => 'ab_sms'])
            ->assertJsonMissing(['code' => 'inactive_type']);
    }

    public function test_service_show_returns_service_by_code(): void
    {
        $this->seedCatalog();

        $this->getJson('/api/v1/services/cpf')
            ->assertOk()
            ->assertJsonPath('data.code', 'cpf')
            ->assertJsonPath('data.name', 'Dados cadastrais de PF')
            ->assertJsonPath('data.price_formatted', 'R$ 0,29');
    }

    public function test_service_show_returns_404_for_missing_service(): void
    {
        $this->getJson('/api/v1/services/inexistente')
            ->assertNotFound()
            ->assertJsonPath('error.type', 'service_not_found');

        $this->seedCatalog();

        $this->getJson('/api/v1/services/ab_sms')
            ->assertNotFound()
            ->assertJsonPath('error.type', 'service_not_found');
    }

    private function seedCatalog(): void
    {
        DB::table('query_types')->insert([
            [
                'id' => '11111111-1111-1111-1111-111111111111',
                'code' => 'cpf',
                'name' => 'CPF',
                'description' => 'Dados cadastrais de PF',
                'default_credit_cost' => 29,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '22222222-2222-2222-2222-222222222222',
                'code' => 'ab_sms',
                'name' => 'SMS',
                'description' => 'Envio de SMS',
                'default_credit_cost' => 10,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '33333333-3333-3333-3333-333333333333',
                'code' => 'inactive_type',
                'name' => 'Inativo',
                'description' => 'Tipo inativo',
                'default_credit_cost' => 10,
                'status' => 'inactive',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '66666666-6666-6666-6666-666666666666',
                'code' => 'ab_veiculos_dados',
                'name' => 'Veículos — Placa Dados',
                'description' => 'Dados do veículo por placa',
                'default_credit_cost' => 100,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('providers')->insert([
            'id' => '44444444-4444-4444-4444-444444444444',
            'identifier' => 'cpfcnpj',
            'name' => 'CPF.CNPJ',
            'status' => 'enabled',
            'environment' => 'production',
            'base_url' => 'https://example.com',
            'credentials' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('provider_capabilities')->insert([
            [
                'id' => '55555555-5555-5555-5555-555555555555',
                'provider_id' => '44444444-4444-4444-4444-444444444444',
                'query_type' => 'cpf',
                'enabled' => true,
                'priority' => 1,
                'price_cents' => 29,
                'config' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '77777777-7777-7777-7777-777777777777',
                'provider_id' => '44444444-4444-4444-4444-444444444444',
                'query_type' => 'ab_veiculos_dados',
                'enabled' => true,
                'priority' => 1,
                'price_cents' => 100,
                'config' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '88888888-8888-8888-8888-888888888888',
                'provider_id' => '44444444-4444-4444-4444-444444444444',
                'query_type' => 'ab_sms',
                'enabled' => false,
                'priority' => 1,
                'price_cents' => 10,
                'config' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Consultation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Modules\Consultation\Infrastructure\Documentation\ConsultQueryTypeDocumentation;
use Tests\TestCase;

final class ConsultQueryTypeDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_documentation_lists_enabled_user_routes_without_provider_names(): void
    {
        $this->seedCatalog();

        $markdown = app(ConsultQueryTypeDocumentation::class)->endpointDescription();

        $this->assertStringContainsString('`cpf`', $markdown);
        $this->assertStringContainsString('`ab_veiculos_dados`', $markdown);
        $this->assertStringNotContainsString('APIBrasil', $markdown);
        $this->assertStringNotContainsString('api_brasil', $markdown);
        $this->assertStringNotContainsString('cpfcnpj', $markdown);
        $this->assertStringNotContainsString('`ab_sms`', $markdown);
        $this->assertStringNotContainsString('`ab_ia_llama`', $markdown);
        $this->assertStringContainsString('Veículos', $markdown);
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
            'id' => '33333333-3333-3333-3333-333333333333',
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
                'id' => '44444444-4444-4444-4444-444444444444',
                'provider_id' => '33333333-3333-3333-3333-333333333333',
                'query_type' => 'cpf',
                'enabled' => true,
                'priority' => 1,
                'price_cents' => 29,
                'config' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '55555555-5555-5555-5555-555555555555',
                'provider_id' => '33333333-3333-3333-3333-333333333333',
                'query_type' => 'ab_sms',
                'enabled' => false,
                'priority' => 1,
                'price_cents' => 10,
                'config' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '77777777-7777-7777-7777-777777777777',
                'provider_id' => '33333333-3333-3333-3333-333333333333',
                'query_type' => 'ab_veiculos_dados',
                'enabled' => true,
                'priority' => 1,
                'price_cents' => 100,
                'config' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

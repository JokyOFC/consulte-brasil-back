<?php

declare(strict_types=1);

use App\Support\Pricing;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderCapabilityModel;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;

return new class extends Migration
{
    private const CODE = 'cnpj_creditos_simples';

    public function up(): void
    {
        $providerId = ProviderModel::query()->where('identifier', 'api_brasil')->value('id');

        if ($providerId === null) {
            return;
        }

        $costCents = 150;
        $priceCents = Pricing::sellPriceCents($costCents);

        if (! DB::table('query_types')->where('code', self::CODE)->exists()) {
            DB::table('query_types')->insert([
                'id' => (string) Str::uuid(),
                'code' => self::CODE,
                'name' => 'CNPJ — Créditos simples',
                'description' => 'Consulta de créditos simples para pessoa jurídica.',
                'default_credit_cost' => $priceCents,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $exists = ProviderCapabilityModel::query()
            ->where('provider_id', $providerId)
            ->where('query_type', self::CODE)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('provider_capabilities')->insert([
            'id' => (string) Str::uuid(),
            'provider_id' => $providerId,
            'query_type' => self::CODE,
            'priority' => 10,
            'price_cents' => $priceCents,
            'cost_cents' => $costCents,
            'enabled' => true,
            'config' => json_encode([
                'endpoint' => 'dados/cnpj/credits',
                'body_key' => 'cnpj',
                'device_group' => 'cnpj',
                'body' => ['tipo' => 'creditos-simples-pj'],
                'homolog' => true,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $providerId = ProviderModel::query()->where('identifier', 'api_brasil')->value('id');

        if ($providerId !== null) {
            DB::table('provider_capabilities')
                ->where('provider_id', $providerId)
                ->where('query_type', self::CODE)
                ->delete();
        }

        DB::table('query_types')->where('code', self::CODE)->delete();
    }
};

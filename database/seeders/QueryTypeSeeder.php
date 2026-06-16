<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Pricing;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Contracts\IdGenerator;

final class QueryTypeSeeder extends Seeder
{
    public function run(): void
    {
        // O valor abaixo é o CUSTO do provedor (centavos); o fallback de preço
        // do tipo (default_credit_cost) já é gravado com a margem da plataforma.
        $catalog = [
            ['cpf', 'Consulta CPF', 'Dados cadastrais de pessoa física.', 29],
            ['cnpj', 'Consulta CNPJ', 'Dados cadastrais de pessoa jurídica.', 51],
        ];

        $ids = app(IdGenerator::class);

        foreach ($catalog as [$code, $name, $description, $cost]) {
            if (DB::table('query_types')->where('code', $code)->exists()) {
                continue;
            }

            $cacheTtl = config("consultation.cache_ttl_by_query_type.{$code}")
                ?? config('consultation.default_cache_ttl_seconds', 86400);

            DB::table('query_types')->insert([
                'id' => $ids->generate(),
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'default_credit_cost' => Pricing::sellPriceCents($cost),
                'cache_ttl_seconds' => $cacheTtl,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

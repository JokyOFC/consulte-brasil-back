<?php

declare(strict_types=1);

use App\Support\Pricing;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill único do modelo "custo + margem":
 *  - capabilities existentes: o preço atual vira o CUSTO do provedor e o novo
 *    preço de venda passa a ser custo + margem da plataforma;
 *  - query_types: o custo default (fallback de preço) também recebe a margem.
 *
 * Roda apenas onde ainda não há custo definido (cost_cents nulo), então é
 * seguro em instalações novas (tabelas vazias) — os seeders já gravam o custo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('provider_capabilities')
            ->whereNull('cost_cents')
            ->get(['id', 'price_cents']);

        foreach ($rows as $row) {
            $cost = (int) $row->price_cents;
            DB::table('provider_capabilities')
                ->where('id', $row->id)
                ->update([
                    'cost_cents' => $cost,
                    'price_cents' => Pricing::sellPriceCents($cost),
                    'updated_at' => now(),
                ]);
        }

        // Fallback de preço por tipo: aplica a mesma margem uma única vez.
        if (DB::table('query_types')->exists()) {
            foreach (DB::table('query_types')->get(['id', 'default_credit_cost']) as $type) {
                DB::table('query_types')
                    ->where('id', $type->id)
                    ->update([
                        'default_credit_cost' => Pricing::sellPriceCents((int) $type->default_credit_cost),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Sem reversão exata: a margem aplicada não é desfeita automaticamente.
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Congela o preço na assinatura (snapshot na contratação). A cobrança
 * recorrente passa a usar subscriptions.price_cents, de modo que alterar o
 * preço do plano NÃO afeta quem já assinou — a menos que o admin opte por
 * aplicar o novo preço aos assinantes atuais.
 *
 * Backfill: assinaturas existentes herdam o preço vigente do plano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'price_cents')) {
                $table->unsignedInteger('price_cents')->nullable()->after('plan_id');
            }
            if (! Schema::hasColumn('subscriptions', 'currency')) {
                $table->string('currency', 3)->nullable()->after('price_cents');
            }
        });

        DB::table('subscriptions')
            ->whereNull('price_cents')
            ->update([
                'price_cents' => DB::raw('(select price_cents from plans where plans.id = subscriptions.plan_id)'),
                'currency' => DB::raw('(select currency from plans where plans.id = subscriptions.plan_id)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            foreach (['price_cents', 'currency'] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

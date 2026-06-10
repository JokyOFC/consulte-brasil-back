<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migração "crédito -> dinheiro": a partir daqui os valores das capabilities
 * representam o PREÇO DE VENDA em centavos de BRL (não mais "créditos").
 * A carteira (wallets) também passa a ser saldo em centavos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('provider_capabilities', 'credit_cost')
            && ! Schema::hasColumn('provider_capabilities', 'price_cents')) {
            Schema::table('provider_capabilities', function (Blueprint $table) {
                $table->renameColumn('credit_cost', 'price_cents');
            });
        }

        if (! Schema::hasColumn('wallets', 'currency')) {
            Schema::table('wallets', function (Blueprint $table) {
                $table->string('currency', 3)->default('BRL')->after('reserved');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('provider_capabilities', 'price_cents')
            && ! Schema::hasColumn('provider_capabilities', 'credit_cost')) {
            Schema::table('provider_capabilities', function (Blueprint $table) {
                $table->renameColumn('price_cents', 'credit_cost');
            });
        }

        if (Schema::hasColumn('wallets', 'currency')) {
            Schema::table('wallets', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
    }
};

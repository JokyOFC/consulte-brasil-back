<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custo do provedor (em centavos) por capability. O preço de venda
 * (price_cents) passa a ser derivado do custo + margem da plataforma.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('provider_capabilities', 'cost_cents')) {
            Schema::table('provider_capabilities', function (Blueprint $table): void {
                $table->unsignedInteger('cost_cents')->nullable()->after('price_cents');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('provider_capabilities', 'cost_cents')) {
            Schema::table('provider_capabilities', function (Blueprint $table): void {
                $table->dropColumn('cost_cents');
            });
        }
    }
};

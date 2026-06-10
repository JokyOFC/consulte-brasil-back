<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enriquece subscriptions para suportar cobrança via Mercado Pago:
 * assinatura recorrente automática (Preapproval) ou fatura manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'mp_preapproval_id')) {
                $table->string('mp_preapproval_id')->nullable()->index()->after('plan_id');
            }
            if (! Schema::hasColumn('subscriptions', 'payment_method')) {
                // credit_card (recorrência automática) | manual (fatura PIX/boleto).
                $table->string('payment_method', 20)->nullable()->after('mp_preapproval_id');
            }
            if (! Schema::hasColumn('subscriptions', 'next_billing_at')) {
                $table->timestamp('next_billing_at')->nullable()->after('renews_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            foreach (['mp_preapproval_id', 'payment_method', 'next_billing_at'] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

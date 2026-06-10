<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Log transacional: 1 linha por chamada, qualquer que seja o status final.
        // LGPD: NÃO persistimos o payload sensível em claro — só hash e metadados.
        Schema::create('consultations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id')->index();
            $table->uuid('api_key_id')->nullable()->index();
            $table->string('query_type', 50)->index();
            $table->uuid('provider_id')->nullable()->index();
            $table->string('status', 20); // success | failed | refunded
            $table->unsignedInteger('credit_cost')->default(0);
            $table->uuid('reservation_id')->nullable()->index();
            $table->char('request_hash', 64);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};

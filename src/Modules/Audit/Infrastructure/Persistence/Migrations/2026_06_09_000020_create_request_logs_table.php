<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trilha de auditoria: 1 linha por request HTTP da API pública,
        // com sucesso ou erro. Corpo/headers ficam cifrados em repouso.
        Schema::create('request_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id')->nullable()->index();
            $table->uuid('api_key_id')->nullable()->index();

            $table->string('method', 10);
            $table->string('path', 1024);
            $table->string('route_name')->nullable();

            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('success')->default(false)->index();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->json('query')->nullable();
            $table->text('headers')->nullable();  // cifrado (encrypted:array)
            $table->text('body')->nullable();     // cifrado (encrypted:array)
            $table->text('response')->nullable();  // resumo cifrado (encrypted:array)

            $table->uuid('consultation_id')->nullable()->index();
            $table->timestamp('created_at')->nullable();

            $table->index(['account_id', 'created_at']);
            $table->index(['route_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_logs');
    }
};

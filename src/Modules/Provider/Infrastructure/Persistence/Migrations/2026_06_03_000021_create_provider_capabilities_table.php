<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quais providers servem quais tipos de consulta, em qual ordem (prioridade) e a que custo.
        Schema::create('provider_capabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('query_type', 50);          // código do tipo (cpf|cnpj|vehicle...)
            $table->integer('priority')->default(100); // menor = tenta primeiro
            $table->unsignedInteger('credit_cost');
            $table->boolean('enabled')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'query_type']);
            $table->index(['query_type', 'enabled', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_capabilities');
    }
};

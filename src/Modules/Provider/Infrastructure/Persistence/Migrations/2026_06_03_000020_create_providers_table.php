<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('identifier', 50)->unique(); // 'api_brasil', etc.
            $table->string('name');
            $table->string('status', 20)->default('enabled'); // enabled | disabled — toggle do admin
            $table->string('base_url')->nullable();
            $table->text('credentials')->nullable(); // JSON encriptado (Crypt::encrypt)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};

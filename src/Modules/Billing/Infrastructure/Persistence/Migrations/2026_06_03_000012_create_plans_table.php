<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('BRL');
            $table->string('billing_period', 20)->default('monthly'); // monthly | one_time
            $table->unsignedInteger('included_credits')->default(0);
            $table->unsignedInteger('overage_price_cents')->nullable();
            $table->json('features')->nullable();
            $table->string('status', 20)->default('active'); // active | archived
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

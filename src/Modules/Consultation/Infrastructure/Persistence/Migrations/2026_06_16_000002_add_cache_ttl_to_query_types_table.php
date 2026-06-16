<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('query_types', function (Blueprint $table) {
            $table->unsignedInteger('cache_ttl_seconds')->nullable()->after('default_credit_cost');
        });

        DB::table('query_types')->where('code', 'cpf')->whereNull('cache_ttl_seconds')->update(['cache_ttl_seconds' => 604800]);
        DB::table('query_types')->where('code', 'cnpj')->whereNull('cache_ttl_seconds')->update(['cache_ttl_seconds' => 604800]);
    }

    public function down(): void
    {
        Schema::table('query_types', function (Blueprint $table) {
            $table->dropColumn('cache_ttl_seconds');
        });
    }
};

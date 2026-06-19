<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_logs', function (Blueprint $table) {
            $table->longText('headers')->nullable()->change();
            $table->longText('body')->nullable()->change();
            $table->longText('response')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('request_logs', function (Blueprint $table) {
            $table->text('headers')->nullable()->change();
            $table->text('body')->nullable()->change();
            $table->text('response')->nullable()->change();
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // FK lógica para accounts.id. Não usamos constraint de banco aqui
            // porque o SQLite (usado nos testes) não permite adicionar FK via
            // ALTER TABLE; a integridade é garantida na camada de aplicação.
            $table->uuid('account_id')->nullable()->after('id')->index();
            $table->string('role', 20)->default('client')->after('account_id'); // admin | client
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['account_id', 'role']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id')->index();

            // topup (recarga de saldo) | invoice (pagamento de fatura).
            $table->string('type', 20);
            // pix | credit_card | boleto.
            $table->string('method', 20);
            // pending | in_process | approved | rejected | cancelled | refunded.
            $table->string('status', 20)->default('pending');

            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');

            // Vínculo opcional com a fatura paga.
            $table->uuid('invoice_id')->nullable()->index();

            // Identificadores do Mercado Pago.
            $table->string('mp_payment_id')->nullable()->unique();
            $table->string('mp_preapproval_id')->nullable()->index();

            // Dados de exibição do checkout transparente.
            $table->text('qr_code')->nullable();          // PIX copia-e-cola
            $table->text('qr_code_base64')->nullable();   // PIX QR (imagem)
            $table->string('ticket_url', 1000)->nullable(); // boleto/pix link
            $table->string('barcode', 255)->nullable();   // linha digitável do boleto

            $table->string('description')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

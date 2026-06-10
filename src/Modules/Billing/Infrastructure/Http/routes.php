<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Modules\Billing\Infrastructure\Http\Controllers\CreditsController;
use Src\Modules\Billing\Infrastructure\Http\Controllers\MercadoPagoWebhookController;

/*
| Rotas do módulo Billing (já sob prefixo /api/v1 e grupo "api").
*/

Route::middleware(['auth:api', 'throttle:60,1'])->group(function () {
    Route::get('/credits', [CreditsController::class, 'show'])->name('credits.show');
});

// Webhook público do Mercado Pago: sem auth (validação por assinatura).
Route::post('/webhooks/mercadopago', MercadoPagoWebhookController::class)
    ->name('webhooks.mercadopago');

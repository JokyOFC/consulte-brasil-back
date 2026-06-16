<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Modules\Billing\Infrastructure\Http\Controllers\CreditsController;
use Src\Modules\Billing\Infrastructure\Http\Controllers\MercadoPagoWebhookController;
use Src\Modules\Billing\Infrastructure\Http\Controllers\PlansController;

/*
| Rotas do módulo Billing (já sob prefixo /api/v1 e grupo "api").
*/

Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/plans', [PlansController::class, 'index'])->name('plans.index');
    Route::get('/plans/{slug}', [PlansController::class, 'show'])->name('plans.show');
});

Route::middleware(['auth:api', 'throttle:60,1'])->group(function () {
    Route::get('/credits', [CreditsController::class, 'show'])->name('credits.show');
});

// Webhook público do Mercado Pago: sem auth (validação por assinatura).
Route::post('/webhooks/mercadopago', MercadoPagoWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.mercadopago');

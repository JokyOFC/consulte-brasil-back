<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Modules\Audit\Infrastructure\Http\Controllers\Client\RequestLogsClientController;
use Src\Modules\Billing\Infrastructure\Http\Controllers\Client\ClientBillingController;
use Src\Modules\Identity\Infrastructure\Http\Controllers\Client\ApiKeysController;

Route::middleware(['auth', 'verified'])
    ->prefix('client')
    ->name('client.')
    ->group(function () {
        Route::get('/api-keys', [ApiKeysController::class, 'index'])->name('api-keys.index');
        Route::post('/api-keys', [ApiKeysController::class, 'store'])->name('api-keys.store');
        Route::delete('/api-keys/{apiKeyId}', [ApiKeysController::class, 'destroy'])->name('api-keys.destroy');

        // Logs das requisições da própria conta
        Route::get('/logs', [RequestLogsClientController::class, 'index'])->name('logs.index');

        // Financeiro: carteira, recarga, faturas e assinatura
        Route::get('/billing', [ClientBillingController::class, 'index'])->name('billing.index');
        Route::get('/billing/payments/{paymentId}/status', [ClientBillingController::class, 'paymentStatus'])->name('billing.payments.status');

        Route::middleware('throttle:10,1')->group(function () {
            Route::post('/billing/topup', [ClientBillingController::class, 'topup'])->name('billing.topup');
            Route::post('/billing/invoices/pay', [ClientBillingController::class, 'payInvoice'])->name('billing.invoices.pay');
            Route::post('/billing/subscribe', [ClientBillingController::class, 'subscribe'])->name('billing.subscribe');
            Route::post('/billing/subscriptions/{subscriptionId}/cancel', [ClientBillingController::class, 'cancelSubscription'])->name('billing.subscriptions.cancel');
            Route::post('/billing/subscriptions/{subscriptionId}/change', [ClientBillingController::class, 'changeSubscription'])->name('billing.subscriptions.change');
        });
    });

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Modules\Audit\Infrastructure\Http\Controllers\Client\RequestLogsClientController;
use Src\Modules\Billing\Infrastructure\Http\Controllers\Client\ClientBillingController;
use Src\Modules\Billing\Infrastructure\Http\Controllers\Client\ClientInvoicesController;
use Src\Modules\Consultation\Infrastructure\Http\Controllers\Client\ConsultationsClientController;
use Src\Modules\Identity\Infrastructure\Http\Controllers\Client\ApiKeysController;
use Src\Modules\Identity\Infrastructure\Http\Controllers\Client\WebhookClientController;
use Src\Modules\Support\Infrastructure\Http\Controllers\Client\SupportTicketController;

Route::middleware(['auth', 'verified'])
    ->prefix('client')
    ->name('client.')
    ->group(function () {
        Route::get('/api-keys', [ApiKeysController::class, 'index'])->name('api-keys.index');
        Route::post('/api-keys', [ApiKeysController::class, 'store'])->name('api-keys.store');
        Route::delete('/api-keys/{apiKeyId}', [ApiKeysController::class, 'destroy'])->name('api-keys.destroy');

        Route::get('/webhook', [WebhookClientController::class, 'index'])->name('webhook.index');
        Route::put('/webhook', [WebhookClientController::class, 'update'])->name('webhook.update');
        Route::post('/webhook/regenerate-secret', [WebhookClientController::class, 'regenerateSecret'])->name('webhook.regenerate-secret');

        // Logs das requisições da própria conta
        Route::get('/logs', [RequestLogsClientController::class, 'index'])->name('logs.index');

        // Consultas via painel web (sessão)
        Route::get('/consultations', [ConsultationsClientController::class, 'index'])->name('consultations.index');
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('/consultations/{queryType}', [ConsultationsClientController::class, 'store'])->name('consultations.store');
        });

        // Financeiro: carteira, recarga, faturas e assinatura
        Route::get('/billing', [ClientBillingController::class, 'index'])->name('billing.index');
        Route::get('/billing/payments/{paymentId}/status', [ClientBillingController::class, 'paymentStatus'])->name('billing.payments.status');

        Route::get('/invoices', [ClientInvoicesController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoiceId}', [ClientInvoicesController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{invoiceId}/pdf', [ClientInvoicesController::class, 'pdf'])->name('invoices.pdf');

        Route::get('/tickets', [SupportTicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/create', [SupportTicketController::class, 'create'])->name('tickets.create');
        Route::post('/tickets', [SupportTicketController::class, 'store'])->name('tickets.store');
        Route::get('/tickets/{ticket}', [SupportTicketController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('tickets.reply');

        Route::middleware('throttle:10,1')->group(function () {
            Route::post('/billing/topup', [ClientBillingController::class, 'topup'])->name('billing.topup');
            Route::post('/billing/invoices/pay', [ClientBillingController::class, 'payInvoice'])->name('billing.invoices.pay');
            Route::post('/billing/invoices/{invoiceId}/cancel', [ClientBillingController::class, 'cancelInvoice'])->name('billing.invoices.cancel');
            Route::post('/billing/subscribe', [ClientBillingController::class, 'subscribe'])->name('billing.subscribe');
            Route::post('/billing/subscriptions/{subscriptionId}/cancel', [ClientBillingController::class, 'cancelSubscription'])->name('billing.subscriptions.cancel');
            Route::post('/billing/subscriptions/{subscriptionId}/change', [ClientBillingController::class, 'changeSubscription'])->name('billing.subscriptions.change');
        });
    });

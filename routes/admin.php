<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\AdminDashboardController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;
use Src\Modules\Audit\Infrastructure\Http\Controllers\Admin\RequestLogsAdminController;
use Src\Modules\Billing\Infrastructure\Http\Controllers\Admin\FinanceAdminController;
use Src\Modules\Billing\Infrastructure\Http\Controllers\Admin\PlansAdminController;
use Src\Modules\Consultation\Infrastructure\Http\Controllers\Admin\QueryTypesAdminController;
use Src\Modules\Identity\Infrastructure\Http\Controllers\Admin\AccountsAdminController;
use Src\Modules\Provider\Infrastructure\Http\Controllers\Admin\ProvidersAdminController;

Route::middleware(['auth', 'verified', 'role:admin', 'throttle:60,1'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');

        // Clientes
        Route::get('/accounts', [AccountsAdminController::class, 'index'])->name('accounts.index');
        Route::get('/accounts/{accountId}', [AccountsAdminController::class, 'show'])->name('accounts.show');
        Route::post('/accounts', [AccountsAdminController::class, 'store'])->name('accounts.store');
        Route::post('/accounts/{accountId}/adjust', [AccountsAdminController::class, 'adjustCredits'])
            ->middleware(RequirePassword::class)
            ->name('accounts.adjust');
        Route::post('/accounts/{accountId}/assign-plan', [AccountsAdminController::class, 'assignPlan'])
            ->middleware(RequirePassword::class)
            ->name('accounts.assign-plan');

        // Financeiro
        Route::get('/finance', [FinanceAdminController::class, 'index'])->name('finance.index');

        // Planos
        Route::get('/plans', [PlansAdminController::class, 'index'])->name('plans.index');
        Route::get('/plans/{planId}', [PlansAdminController::class, 'show'])->name('plans.show');
        Route::post('/plans', [PlansAdminController::class, 'store'])->name('plans.store');
        Route::put('/plans/{planId}', [PlansAdminController::class, 'update'])->name('plans.update');

        // Providers
        Route::get('/providers', [ProvidersAdminController::class, 'index'])->name('providers.index');
        Route::get('/providers/{providerId}', [ProvidersAdminController::class, 'show'])->name('providers.show');
        Route::get('/providers/{providerId}/balance', [ProvidersAdminController::class, 'balance'])->name('providers.balance');
        Route::post('/providers', [ProvidersAdminController::class, 'store'])->name('providers.store');
        Route::put('/providers/{providerId}', [ProvidersAdminController::class, 'update'])
            ->middleware(RequirePassword::class)
            ->name('providers.update');
        Route::post('/providers/{providerId}/toggle', [ProvidersAdminController::class, 'toggle'])->name('providers.toggle');
        Route::post('/providers/{providerId}/environment', [ProvidersAdminController::class, 'switchEnvironment'])->name('providers.environment');
        Route::post('/providers/{providerId}/capabilities', [ProvidersAdminController::class, 'upsertCapability'])
            ->middleware(RequirePassword::class)
            ->name('providers.capabilities.upsert');

        // Tipos de consulta
        Route::get('/query-types', [QueryTypesAdminController::class, 'index'])->name('query-types.index');
        Route::put('/query-types/{queryTypeId}', [QueryTypesAdminController::class, 'update'])->name('query-types.update');

        // Logs de requisições (auditoria)
        Route::get('/logs', [RequestLogsAdminController::class, 'index'])->name('logs.index');

        // Configurações gerais (tempo de sessão, etc.)
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])
            ->middleware(RequirePassword::class)
            ->name('settings.update');
    });

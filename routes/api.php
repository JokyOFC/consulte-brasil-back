<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Modules\Consultation\Infrastructure\Http\Controllers\PingController;
use Src\Modules\Consultation\Infrastructure\Http\Controllers\ServicesController;

/*
|--------------------------------------------------------------------------
| API pública — versão 1
|--------------------------------------------------------------------------
|
| Este arquivo é o ponto de entrada da API pública (prefixo /api). Mantemos
| o arquivo central enxuto: cada módulo (bounded context) declara suas
| próprias rotas em src/Modules/<Modulo>/Infrastructure/Http/routes.php e
| elas são auto-descobertas e carregadas sob o prefixo /api/v1.
|
| Rotas públicas críticas (catálogo) ficam registradas aqui também para
| garantir presença após `php artisan route:cache` no deploy.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/ping', PingController::class)->name('ping');

    Route::middleware(['throttle:60,1'])->group(function () {
        Route::get('/services', [ServicesController::class, 'index'])->name('services.index');
        Route::get('/services/{code}', [ServicesController::class, 'show'])
            ->where('code', '[a-z][a-z0-9_]{1,49}')
            ->name('services.show');
    });

    foreach (glob(base_path('src/Modules/*/Infrastructure/Http/routes.php')) ?: [] as $moduleRoutes) {
        require $moduleRoutes;
    }
});

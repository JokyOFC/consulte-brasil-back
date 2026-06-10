<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Modules\Consultation\Infrastructure\Http\Controllers\PingController;

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
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/ping', PingController::class)->name('ping');

    foreach (glob(base_path('src/Modules/*/Infrastructure/Http/routes.php')) ?: [] as $moduleRoutes) {
        require $moduleRoutes;
    }
});

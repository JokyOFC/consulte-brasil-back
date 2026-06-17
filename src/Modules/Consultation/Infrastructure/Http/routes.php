<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Modules\Consultation\Infrastructure\Http\Controllers\ConsultController;

/*
| Rotas do módulo Consultation (já sob prefixo /api/v1).
| Catálogo público (/services) registrado em routes/api.php.
*/

Route::middleware(['auth:api', 'throttle:60,1'])->group(function () {
    Route::post('/consult/{queryType}', ConsultController::class)
        ->where('queryType', '[a-z][a-z0-9_]{1,49}')
        ->name('consult.execute');
});

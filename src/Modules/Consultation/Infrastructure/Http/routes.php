<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Modules\Consultation\Infrastructure\Http\Controllers\ConsultController;
use Src\Modules\Consultation\Infrastructure\Http\Controllers\ServicesController;

/*
| Rotas do módulo Consultation (já sob prefixo /api/v1).
*/

Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/services', [ServicesController::class, 'index'])->name('services.index');
    Route::get('/services/{code}', [ServicesController::class, 'show'])
        ->where('code', '[a-z][a-z0-9_]{1,49}')
        ->name('services.show');
});

Route::middleware(['auth:api', 'throttle:60,1'])->group(function () {
    Route::post('/consult/{queryType}', ConsultController::class)
        ->where('queryType', '[a-z][a-z0-9_]{1,49}')
        ->name('consult.execute');
});

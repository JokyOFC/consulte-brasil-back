<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Modules\Identity\Infrastructure\Http\Controllers\AccountController;

/*
| Rotas do módulo Identity. Carregadas por routes/api.php já sob o
| prefixo /api/v1 e o grupo de middleware "api".
*/

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AccountController::class, 'me'])->name('account.me');
});

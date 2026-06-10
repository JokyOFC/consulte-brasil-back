<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Tarefas agendadas — execute `php artisan schedule:work` em produção
| (ou um cron 1-em-1-min apontando para `artisan schedule:run`).
*/

// Reconciliação do cache de saldo (Redis) a partir do MySQL — cura deriva.
Schedule::command('billing:reconcile-balances')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Retenção LGPD: anonimiza request_hash de consultas com mais de 180 dias.
Schedule::command('consultation:purge-request-hash --days=180')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();

// Faturamento recorrente: marca faturas vencidas e gera faturas do ciclo.
Schedule::command('billing:run-recurring')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();

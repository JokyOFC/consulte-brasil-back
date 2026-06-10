<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Console;

use Illuminate\Console\Command;
use Src\Modules\Billing\Application\UseCase\RunRecurringBilling;

/**
 * Executa o ciclo de faturamento recorrente: marca faturas vencidas e gera
 * as faturas do próximo ciclo das assinaturas manuais.
 */
final class RunRecurringBillingCommand extends Command
{
    protected $signature = 'billing:run-recurring';

    protected $description = 'Marca faturas vencidas e gera faturas do ciclo das assinaturas manuais.';

    public function handle(RunRecurringBilling $useCase): int
    {
        $result = $useCase->handle();

        $this->info(sprintf(
            'Faturas vencidas: %d · Faturas geradas: %d',
            $result['overdue'],
            $result['invoices_generated'],
        ));

        return self::SUCCESS;
    }
}

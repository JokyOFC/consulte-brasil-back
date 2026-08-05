<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Modules\Billing\Application\Service\InvoiceNumberGenerator;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\InvoiceModel;

final class BackfillInvoiceNumbersCommand extends Command
{
    protected $signature = 'billing:backfill-invoice-numbers';

    protected $description = 'Atribui número FAT-ANO-SEQ às faturas que ainda não possuem.';

    public function handle(InvoiceNumberGenerator $numbers): int
    {
        $query = InvoiceModel::query()
            ->whereNull('number')
            ->orderBy('created_at')
            ->orderBy('id');

        $count = 0;

        $query->each(function (InvoiceModel $invoice) use ($numbers, &$count): void {
            $year = $invoice->created_at?->year ?? (int) date('Y');

            DB::transaction(function () use ($invoice, $numbers, $year): void {
                $invoice->number = $numbers->next($year);
                $invoice->save();
            });

            $count++;
        });

        $this->info("Números atribuídos: {$count}");

        return self::SUCCESS;
    }
}

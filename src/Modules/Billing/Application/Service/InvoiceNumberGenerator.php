<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\Service;

use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Contracts\Clock;

/**
 * Gera números humanos únicos no formato FAT-{ano}-{sequencial 6 dígitos}.
 */
final readonly class InvoiceNumberGenerator
{
    public function __construct(
        private Clock $clock,
    ) {}

    public function next(?int $year = null): string
    {
        $year ??= (int) $this->clock->now()->format('Y');

        return DB::transaction(function () use ($year): string {
            $row = DB::table('invoice_sequences')->where('year', $year)->lockForUpdate()->first();

            if ($row === null) {
                DB::table('invoice_sequences')->insert([
                    'year' => $year,
                    'last_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return sprintf('FAT-%d-%06d', $year, 1);
            }

            $next = ((int) $row->last_value) + 1;

            DB::table('invoice_sequences')
                ->where('year', $year)
                ->update([
                    'last_value' => $next,
                    'updated_at' => now(),
                ]);

            return sprintf('FAT-%d-%06d', $year, $next);
        });
    }
}

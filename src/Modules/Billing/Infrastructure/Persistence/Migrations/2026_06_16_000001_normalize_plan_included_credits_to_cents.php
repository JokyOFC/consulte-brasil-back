<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Converte saldo incluso legado (unidades inteiras) para centavos de BRL.
 * Só altera valores exatos do seed antigo para evitar conversão duplicada.
 */
return new class extends Migration
{
    /** @var array<string, array{from: int, to: int}> */
    private array $legacyMap = [
        'starter' => ['from' => 100, 'to' => 10000],
        'growth' => ['from' => 500, 'to' => 50000],
        'scale' => ['from' => 2000, 'to' => 200000],
    ];

    public function up(): void
    {
        foreach ($this->legacyMap as $slug => $map) {
            DB::table('plans')
                ->where('slug', $slug)
                ->where('included_credits', $map['from'])
                ->update(['included_credits' => $map['to']]);
        }
    }

    public function down(): void
    {
        foreach ($this->legacyMap as $slug => $map) {
            DB::table('plans')
                ->where('slug', $slug)
                ->where('included_credits', $map['to'])
                ->update(['included_credits' => $map['from']]);
        }
    }
};

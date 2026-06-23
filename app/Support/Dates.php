<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Serializa timestamps para o frontend (Inertia/API).
 *
 * O MySQL guarda timestamps em UTC. Consultas via DB::table() devolvem a
 * string crua — Carbon::parse() sem fuso assume o timezone da app e erra 3h.
 * Eloquent com cast datetime já devolve o instante correto; este helper unifica
 * os dois casos num ISO 8601 no fuso configurado (America/Sao_Paulo).
 */
final class Dates
{
    public static function toFrontendIso(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)
                ->timezone(config('app.timezone'))
                ->toIso8601String();
        }

        return Carbon::parse((string) $value, 'UTC')
            ->timezone(config('app.timezone'))
            ->toIso8601String();
    }
}

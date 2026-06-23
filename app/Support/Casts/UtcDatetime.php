<?php

declare(strict_types=1);

namespace App\Support\Casts;

use App\Support\Dates;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Timestamps gravados em UTC no MySQL e expostos no fuso da aplicação.
 *
 * Colunas TIMESTAMP do MySQL devolvem a string no fuso da sessão PDO
 * (config database.connections.*.timezone). O cast normaliza para UTC
 * na leitura; a exibição fica a cargo de Dates::toFrontendIso().
 */
final class UtcDatetime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Dates::parseStoredTimestamp((string) $value, $model->getConnectionName());
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
    }
}

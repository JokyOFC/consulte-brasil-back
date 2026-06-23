<?php

declare(strict_types=1);

namespace App\Support\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Timestamps gravados em UTC no MySQL e expostos no fuso da aplicação.
 *
 * O cast datetime padrão do Eloquent interpreta a string do banco como horário
 * local (app.timezone), mas o MySQL/Laravel persistem em UTC — isso deslocava
 * as datas em 3h (ex.: logs de API no painel do cliente).
 */
final class UtcDatetime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value, 'UTC')->timezone(config('app.timezone'));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
    }
}

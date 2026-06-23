<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Serializa timestamps para o frontend (Inertia/API).
 *
 * O MySQL persiste instantes em UTC (via TIMESTAMP ou gravação Laravel).
 * A string lida do banco reflete o fuso da sessão PDO; parseStoredTimestamp()
 * normaliza para UTC antes de converter para display_timezone na serialização.
 */
final class Dates
{
    public static function displayTimezone(): string
    {
        return (string) config('app.display_timezone', 'America/Sao_Paulo');
    }

    public static function toFrontendIso(DateTimeInterface|string|null $value, ?string $connection = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::parseStoredTimestamp($value, $connection)
            ->timezone(self::displayTimezone())
            ->toIso8601String();
    }

    /**
     * Interpreta valor vindo do banco (string na sessão PDO ou Carbon) como instante UTC.
     */
    public static function parseStoredTimestamp(DateTimeInterface|string $value, ?string $connection = null): Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->utc();
        }

        $string = (string) $value;

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $string) === 1) {
            return Carbon::createFromFormat('Y-m-d H:i:s', $string, self::databaseTimezone($connection))->utc();
        }

        return Carbon::parse($string)->utc();
    }

    /** Fuso configurado na conexão PDO (MySQL SET time_zone). */
    public static function databaseTimezone(?string $connection = null): string
    {
        $connection ??= (string) config('database.default');
        $configured = config("database.connections.{$connection}.timezone");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return '+00:00';
    }
}

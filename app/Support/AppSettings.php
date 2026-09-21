<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Acesso às configurações gerais do sistema (tabela `settings`) com cache.
 *
 * Centraliza os defaults e os limites de cada configuração para evitar valores
 * inválidos persistidos pelo admin.
 */
final class AppSettings
{
    /** Tempo de sessão online (minutos de inatividade até o logout). */
    public const SESSION_TIMEOUT_MINUTES = 'session_timeout_minutes';

    public const SESSION_TIMEOUT_DEFAULT = 120;

    public const SESSION_TIMEOUT_MIN = 1;

    public const SESSION_TIMEOUT_MAX = 1440;

    /** Exigir confirmação de email dos clientes (registro e acesso ao painel). */
    public const CLIENT_EMAIL_VERIFICATION_ENABLED = 'client_email_verification_enabled';

    /** 2FA dos clientes (desafio no login e gestão em Configurações > Segurança). */
    public const CLIENT_TWO_FACTOR_ENABLED = 'client_two_factor_enabled';

    private const CACHE_PREFIX = 'app_setting:';

    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            return Cache::rememberForever(
                self::CACHE_PREFIX.$key,
                static fn () => Setting::query()->whereKey($key)->value('value') ?? $default,
            );
        } catch (\Throwable) {
            // Antes da migration rodar (ou em falha de cache/banco) cai no default.
            return $default;
        }
    }

    public static function set(string $key, ?string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /** Tempo de sessão (minutos), sempre dentro dos limites permitidos. */
    public static function sessionTimeoutMinutes(): int
    {
        $value = (int) self::get(self::SESSION_TIMEOUT_MINUTES, (string) self::SESSION_TIMEOUT_DEFAULT);

        return max(self::SESSION_TIMEOUT_MIN, min(self::SESSION_TIMEOUT_MAX, $value));
    }

    public static function bool(string $key, bool $default): bool
    {
        return self::get($key, $default ? '1' : '0') === '1';
    }

    public static function setBool(string $key, bool $value): void
    {
        self::set($key, $value ? '1' : '0');
    }

    /** Clientes precisam confirmar o email? Ligado por padrão. */
    public static function clientEmailVerificationEnabled(): bool
    {
        return self::bool(self::CLIENT_EMAIL_VERIFICATION_ENABLED, true);
    }

    /** 2FA disponível/exigido para clientes? Ligado por padrão. */
    public static function clientTwoFactorEnabled(): bool
    {
        return self::bool(self::CLIENT_TWO_FACTOR_ENABLED, true);
    }
}

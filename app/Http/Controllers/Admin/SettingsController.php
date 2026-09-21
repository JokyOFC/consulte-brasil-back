<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configurações gerais do sistema (admin): tempo de sessão online e regras
 * de acesso dos clientes (confirmação de email e 2FA).
 */
final class SettingsController
{
    public function index(): Response
    {
        return Inertia::render('admin/settings/index', [
            'settings' => [
                'session_timeout_minutes' => AppSettings::sessionTimeoutMinutes(),
                'client_email_verification_enabled' => AppSettings::clientEmailVerificationEnabled(),
                'client_two_factor_enabled' => AppSettings::clientTwoFactorEnabled(),
            ],
            'limits' => [
                'session_timeout_min' => AppSettings::SESSION_TIMEOUT_MIN,
                'session_timeout_max' => AppSettings::SESSION_TIMEOUT_MAX,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Cada bloco da tela salva só os seus campos ('sometimes').
        $data = $request->validate([
            'session_timeout_minutes' => [
                'sometimes',
                'required',
                'integer',
                'min:'.AppSettings::SESSION_TIMEOUT_MIN,
                'max:'.AppSettings::SESSION_TIMEOUT_MAX,
            ],
            'client_email_verification_enabled' => ['sometimes', 'required', 'boolean'],
            'client_two_factor_enabled' => ['sometimes', 'required', 'boolean'],
        ]);

        if (array_key_exists('session_timeout_minutes', $data)) {
            AppSettings::set(
                AppSettings::SESSION_TIMEOUT_MINUTES,
                (string) $data['session_timeout_minutes'],
            );
        }

        if (array_key_exists('client_email_verification_enabled', $data)) {
            AppSettings::setBool(
                AppSettings::CLIENT_EMAIL_VERIFICATION_ENABLED,
                (bool) $data['client_email_verification_enabled'],
            );
        }

        if (array_key_exists('client_two_factor_enabled', $data)) {
            AppSettings::setBool(
                AppSettings::CLIENT_TWO_FACTOR_ENABLED,
                (bool) $data['client_two_factor_enabled'],
            );
        }

        return back()->with('success', 'Configurações atualizadas.');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configurações gerais do sistema (admin). Hoje: tempo de sessão online.
 */
final class SettingsController
{
    public function index(): Response
    {
        return Inertia::render('admin/settings/index', [
            'settings' => [
                'session_timeout_minutes' => AppSettings::sessionTimeoutMinutes(),
            ],
            'limits' => [
                'session_timeout_min' => AppSettings::SESSION_TIMEOUT_MIN,
                'session_timeout_max' => AppSettings::SESSION_TIMEOUT_MAX,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'session_timeout_minutes' => [
                'required',
                'integer',
                'min:'.AppSettings::SESSION_TIMEOUT_MIN,
                'max:'.AppSettings::SESSION_TIMEOUT_MAX,
            ],
        ]);

        AppSettings::set(
            AppSettings::SESSION_TIMEOUT_MINUTES,
            (string) $data['session_timeout_minutes'],
        );

        return back()->with('success', 'Configurações atualizadas.');
    }
}

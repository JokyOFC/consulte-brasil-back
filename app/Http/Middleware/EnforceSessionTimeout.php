<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AppSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Desloga usuários autenticados após um período de inatividade configurável
 * pelo admin (AppSettings::SESSION_TIMEOUT_MINUTES). É autoritativo no servidor:
 * a cada requisição compara o "agora" com o último acesso registrado na sessão.
 */
final class EnforceSessionTimeout
{
    private const LAST_ACTIVITY_KEY = 'last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $session = $request->session();
        $timeoutSeconds = AppSettings::sessionTimeoutMinutes() * 60;
        $lastActivity = (int) $session->get(self::LAST_ACTIVITY_KEY, 0);
        $now = time();

        if ($lastActivity > 0 && ($now - $lastActivity) > $timeoutSeconds) {
            return $this->logout($request);
        }

        $session->put(self::LAST_ACTIVITY_KEY, $now);

        return $next($request);
    }

    private function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = 'Sua sessão expirou por inatividade. Faça login novamente.';

        // Inertia espera um redirect "externo" (409) para trocar de contexto.
        if ($request->header('X-Inertia')) {
            return inertia()->location(route('login'));
        }

        return redirect()->route('login')->with('error', $message);
    }
}

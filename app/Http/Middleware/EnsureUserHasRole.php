<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de role para as rotas web administrativas. Confere a coluna
 * users.role contra o(s) papel(éis) declarado(s) na rota — ex.: ->middleware('role:admin').
 */
final class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null || ! in_array((string) ($user->role ?? ''), $roles, true)) {
            abort(403, 'Forbidden: role required.');
        }

        return $next($request);
    }
}

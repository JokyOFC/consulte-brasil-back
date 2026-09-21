<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified as BaseEnsureEmailIsVerified;

/**
 * Substitui o alias 'verified' do framework: quando o admin desliga a
 * confirmação de email dos clientes (AppSettings), o cliente não verificado
 * passa direto. Admins continuam sempre obrigados a verificar.
 */
final class EnsureEmailIsVerified extends BaseEnsureEmailIsVerified
{
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->emailVerificationRequired()) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}

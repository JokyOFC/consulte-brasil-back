<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\AppSettings;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

/**
 * Pula o desafio de 2FA no login quando o admin desligou o 2FA dos clientes
 * (Admin > Configurações). Admins continuam sempre sujeitos ao desafio.
 */
class RedirectIfTwoFactorRequired extends RedirectIfTwoFactorAuthenticatable
{
    public function handle($request, $next)
    {
        if (! AppSettings::clientTwoFactorEnabled()) {
            $user = $this->guard->getProvider()->retrieveByCredentials([
                Fortify::username() => $request->input(Fortify::username()),
            ]);

            // Sem desafio: a senha é validada no próximo passo do pipeline
            // (AttemptToAuthenticate), com o rate limit de sempre.
            if ($user instanceof User && ! $user->twoFactorAvailable()) {
                return $next($request);
            }
        }

        return parent::handle($request, $next);
    }
}

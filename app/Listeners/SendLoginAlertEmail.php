<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Mail\TransactionalMailer;

final class SendLoginAlertEmail
{
    public function __construct(private TransactionalMailer $mailer) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $user = $event->user;
        $ip = request()->ip() ?? 'Desconhecido';
        $userAgent = request()->userAgent() ?? 'Desconhecido';

        $isNewContext = $this->isNewLoginContext($user, $ip, $userAgent);

        if ($isNewContext) {
            $this->mailer->sendLoginAlert(
                user: $user,
                ipAddress: $ip,
                userAgent: $userAgent,
                loggedAt: Carbon::now()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            );
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'last_login_user_agent' => $userAgent,
        ])->save();
    }

    private function isNewLoginContext(User $user, string $ip, string $userAgent): bool
    {
        if ($user->last_login_ip === null) {
            return true;
        }

        if ($user->last_login_ip !== $ip) {
            return true;
        }

        if ($user->last_login_user_agent !== null && $user->last_login_user_agent !== $userAgent) {
            return true;
        }

        return false;
    }
}

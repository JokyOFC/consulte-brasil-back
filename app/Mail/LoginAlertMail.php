<?php

namespace App\Mail;

use App\Models\User;

final class LoginAlertMail extends BrandedMailable
{
    public function __construct(
        public User $user,
        public string $ipAddress,
        public string $userAgent,
        public string $loggedAt,
    ) {}

    protected function mailSubject(): string
    {
        return 'Novo acesso à sua conta';
    }

    protected function mailTitle(): string
    {
        return 'Novo acesso detectado';
    }

    protected function mailPreview(): string
    {
        return 'Um novo login foi realizado na sua conta.';
    }

    protected function mailView(): string
    {
        return 'mail.login-alert';
    }

    protected function mailData(): array
    {
        return [
            'userName' => $this->user->name,
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->truncateUserAgent($this->userAgent),
            'loggedAt' => $this->loggedAt,
            'passwordUrl' => url('/settings/security'),
        ];
    }

    private function truncateUserAgent(string $userAgent): string
    {
        return strlen($userAgent) > 120 ? substr($userAgent, 0, 117).'...' : $userAgent;
    }
}

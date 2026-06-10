<?php

namespace App\Mail;

use App\Models\User;

final class WelcomeMail extends BrandedMailable
{
    public function __construct(public User $user) {}

    protected function mailSubject(): string
    {
        return 'Bem-vindo ao Consulte Brasil';
    }

    protected function mailTitle(): string
    {
        return 'Sua conta foi criada';
    }

    protected function mailPreview(): string
    {
        return 'Comece a consultar dados oficiais do Brasil via API.';
    }

    protected function mailView(): string
    {
        return 'mail.welcome';
    }

    protected function mailData(): array
    {
        return [
            'userName' => $this->user->name,
            'dashboardUrl' => url('/dashboard'),
        ];
    }
}

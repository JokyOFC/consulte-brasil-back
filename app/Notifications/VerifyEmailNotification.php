<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

class VerifyEmailNotification extends VerifyEmail
{
    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Confirme seu e-mail — Consulte Brasil')
            ->view('mail.verify-email', [
                'subject' => 'Confirme seu e-mail',
                'title' => 'Confirme seu e-mail',
                'preview' => 'Ative sua conta com um clique.',
                'userName' => $notifiable->name,
                'verificationUrl' => $verificationUrl,
                'expireMinutes' => Config::get('auth.verification.expire', 60),
            ]);
    }
}

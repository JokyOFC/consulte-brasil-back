<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Src\Shared\Infrastructure\Mail\TransactionalMailer;

final class SendWelcomeEmail
{
    public function __construct(private TransactionalMailer $mailer) {}

    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->mailer->sendWelcome($event->user);
    }
}

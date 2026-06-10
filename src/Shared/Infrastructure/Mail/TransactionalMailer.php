<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Mail;

use App\Mail\LoginAlertMail;
use App\Mail\PaymentConfirmedMail;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Src\Modules\Billing\Domain\Event\PaymentSettled;

final class TransactionalMailer
{
    public function sendWelcome(User $user): void
    {
        Mail::to($user)->send(new WelcomeMail($user));
    }

    public function sendPaymentConfirmed(User $user, PaymentSettled $event): void
    {
        Mail::to($user)->send(new PaymentConfirmedMail(
            user: $user,
            paymentType: $event->type,
            amountCents: $event->amountCents,
            creditsGranted: $event->creditsGranted,
        ));
    }

    public function sendLoginAlert(User $user, string $ipAddress, string $userAgent, string $loggedAt): void
    {
        Mail::to($user)->send(new LoginAlertMail(
            user: $user,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            loggedAt: $loggedAt,
        ));
    }
}

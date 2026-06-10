<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Listeners;

use App\Models\User;
use Src\Modules\Billing\Domain\Event\PaymentSettled;
use Src\Shared\Infrastructure\Mail\TransactionalMailer;

final class SendPaymentConfirmedEmail
{
    public function __construct(private TransactionalMailer $mailer) {}

    public function handle(PaymentSettled $event): void
    {
        $user = User::query()
            ->where('account_id', $event->accountId)
            ->orderBy('id')
            ->first();

        if ($user === null) {
            return;
        }

        $this->mailer->sendPaymentConfirmed($user, $event);
    }
}

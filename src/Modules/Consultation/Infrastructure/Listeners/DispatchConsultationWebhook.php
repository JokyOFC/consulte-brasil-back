<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Listeners;

use Illuminate\Contracts\Encryption\DecryptException;
use Src\Modules\Consultation\Domain\Entity\Consultation;
use Src\Modules\Consultation\Domain\Event\ConsultationCompleted;
use Src\Modules\Consultation\Infrastructure\Jobs\DeliverConsultationWebhookJob;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;

final class DispatchConsultationWebhook
{
    public function handle(ConsultationCompleted $event): void
    {
        $account = AccountModel::query()->find($event->accountId);

        if ($account === null || $account->webhook_url === null || $account->webhook_url === '') {
            return;
        }

        if ($account->webhook_secret === null || $account->webhook_secret === '') {
            return;
        }

        try {
            $secret = decrypt($account->webhook_secret);
        } catch (DecryptException) {
            return;
        }

        if (! is_string($secret) || $secret === '') {
            return;
        }

        $payload = [
            'event' => 'consultation.completed',
            'consultation_id' => $event->consultationId,
            'status' => $event->status,
            'query_type' => $event->queryType,
            'amount_charged' => $event->creditCost,
            'from_cache' => $event->fromCache,
        ];

        if ($event->providerIdentifier !== null) {
            $payload['provider'] = $event->providerIdentifier;
        }

        if ($event->status === Consultation::STATUS_SUCCESS && $event->data !== null) {
            $payload['data'] = $event->data;
        }

        if ($event->status === Consultation::STATUS_REFUNDED && $event->failureReason !== null) {
            $payload['error'] = ['code' => $event->failureReason];
        }

        DeliverConsultationWebhookJob::dispatch(
            webhookUrl: $account->webhook_url,
            webhookSecret: $secret,
            payload: $payload,
        );
    }
}

<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Domain\Exception\PaymentGatewayError;
use Src\Modules\Billing\Domain\Exception\SubscriptionNotFound;
use Src\Modules\Billing\Domain\Repository\SubscriptionRepository;
use Src\Shared\Application\Contracts\Clock;

/**
 * Cancela uma assinatura: encerra a recorrência automática no Mercado Pago
 * (se houver Preapproval) e marca a assinatura como cancelada.
 */
final readonly class CancelSubscription
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private PaymentGateway $gateway,
        private Clock $clock,
    ) {}

    public function handle(string $subscriptionId, string $accountId): void
    {
        $subscription = $this->subscriptions->findById($subscriptionId);
        if ($subscription === null || $subscription->accountId !== $accountId) {
            throw SubscriptionNotFound::withId($subscriptionId);
        }

        if ($subscription->mpPreapprovalId !== null) {
            try {
                $this->gateway->cancelPreapproval($subscription->mpPreapprovalId);
            } catch (PaymentGatewayError) {
                // Mesmo que o cancelamento remoto falhe, encerramos localmente.
            }
        }

        $subscription->cancel($this->clock->now());
        $this->subscriptions->save($subscription);
    }
}

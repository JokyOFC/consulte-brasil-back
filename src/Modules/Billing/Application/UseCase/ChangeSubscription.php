<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Domain\Exception\PaymentGatewayError;
use Src\Modules\Billing\Domain\Exception\PlanNotFound;
use Src\Modules\Billing\Domain\Exception\SubscriptionNotFound;
use Src\Modules\Billing\Domain\Repository\PlanRepository;
use Src\Modules\Billing\Domain\Repository\SubscriptionRepository;

/**
 * Altera o plano de uma assinatura. Como o valor recorrente muda, qualquer
 * Preapproval existente é cancelado e a assinatura passa a "manual" — o
 * cliente confirma o novo valor no próximo ciclo (ou reassina no cartão).
 */
final readonly class ChangeSubscription
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private PlanRepository $plans,
        private PaymentGateway $gateway,
    ) {}

    public function handle(string $subscriptionId, string $accountId, string $newPlanId): void
    {
        $subscription = $this->subscriptions->findById($subscriptionId);
        if ($subscription === null || $subscription->accountId !== $accountId) {
            throw SubscriptionNotFound::withId($subscriptionId);
        }

        $newPlan = $this->plans->findById($newPlanId);
        if ($newPlan === null) {
            throw PlanNotFound::withId($newPlanId);
        }

        if ($subscription->mpPreapprovalId !== null) {
            try {
                $this->gateway->cancelPreapproval($subscription->mpPreapprovalId);
            } catch (PaymentGatewayError) {
                // Ignora falha remota; segue com a troca local.
            }
            $subscription->mpPreapprovalId = null;
            $subscription->paymentMethod = 'manual';
        }

        $subscription->planId = $newPlanId;
        $subscription->price = $newPlan->price;
        $subscription->reactivate();
        $this->subscriptions->save($subscription);
    }
}

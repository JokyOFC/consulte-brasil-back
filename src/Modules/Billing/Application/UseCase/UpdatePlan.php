<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Psr\Log\LoggerInterface;
use Src\Modules\Billing\Application\DTO\UpdatePlanInput;
use Src\Modules\Billing\Application\DTO\UpdatePlanResult;
use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Domain\Entity\Plan;
use Src\Modules\Billing\Domain\Exception\PaymentGatewayError;
use Src\Modules\Billing\Domain\Repository\PlanRepository;
use Src\Modules\Billing\Domain\ValueObject\BillingPeriod;
use Src\Modules\Billing\Domain\ValueObject\PlanStatus;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\SubscriptionModel;
use Src\Shared\Domain\ValueObject\Money;

/**
 * Atualiza um plano. Como o preço da assinatura é congelado na contratação,
 * mudar o preço aqui só afeta NOVOS assinantes — a menos que o input peça
 * explicitamente para aplicar aos assinantes atuais (repreçamento).
 *
 * No repreçamento, assinaturas no cartão têm o Preapproval atualizado no
 * Mercado Pago; se o MP recusar, a assinatura mantém o preço antigo (a
 * fatura precisa bater com o valor realmente cobrado).
 */
final readonly class UpdatePlan
{
    public function __construct(
        private PlanRepository $plans,
        private PaymentGateway $gateway,
        private LoggerInterface $logger,
    ) {}

    public function handle(UpdatePlanInput $input): UpdatePlanResult
    {
        $plan = $this->plans->findById($input->planId);
        if ($plan === null) {
            throw new \InvalidArgumentException('Plano não encontrado.');
        }

        $priceChanged = $plan->price->cents !== $input->priceCents;

        $plan->name = $input->name;
        $plan->price = Money::of($input->priceCents, $plan->price->currency);
        $plan->billingPeriod = BillingPeriod::from($input->billingPeriod);
        $plan->includedCredits = $input->includedCredits;
        $plan->overagePrice = $input->overagePriceCents !== null
            ? Money::of($input->overagePriceCents, $plan->price->currency)
            : null;
        $plan->status = PlanStatus::from($input->status);

        $this->plans->save($plan);

        if (! $priceChanged || ! $input->applyToExistingSubscribers) {
            return new UpdatePlanResult($plan);
        }

        return $this->repriceExistingSubscriptions($plan);
    }

    private function repriceExistingSubscriptions(Plan $plan): UpdatePlanResult
    {
        $repriced = 0;
        $failures = 0;

        $subscriptions = SubscriptionModel::query()
            ->where('plan_id', $plan->id)
            ->whereIn('status', ['active', 'past_due'])
            ->get();

        foreach ($subscriptions as $subscription) {
            if ($subscription->mp_preapproval_id !== null) {
                try {
                    $this->gateway->updatePreapprovalAmount(
                        (string) $subscription->mp_preapproval_id,
                        $plan->price->cents,
                    );
                } catch (PaymentGatewayError $e) {
                    $failures++;
                    $this->logger->warning('billing.reprice.preapproval_failed', [
                        'subscription_id' => $subscription->id,
                        'mp_preapproval_id' => $subscription->mp_preapproval_id,
                        'plan_id' => $plan->id,
                        'message' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            $subscription->price_cents = $plan->price->cents;
            $subscription->currency = $plan->price->currency;
            $subscription->save();
            $repriced++;
        }

        return new UpdatePlanResult($plan, $repriced, $failures);
    }
}

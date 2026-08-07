<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Illuminate\Support\Facades\DB;
use Src\Modules\Billing\Application\DTO\GrantCreditsInput;
use Src\Modules\Billing\Domain\Exception\PlanNotFound;
use Src\Modules\Billing\Domain\Repository\PlanRepository;
use Src\Modules\Billing\Domain\ValueObject\BillingPeriod;
use Src\Shared\Application\Contracts\Clock;
use Src\Shared\Application\Contracts\IdGenerator;
use Src\Shared\Application\Contracts\TransactionManager;

/**
 * Atribui um plano a uma conta: cria a Subscription e concede os créditos
 * inclusos no plano (via GrantCredits — mesmo caminho do ledger).
 *
 * Idempotente por (account, plan, billing_period) usando a idempotencyKey
 * do grant: o segundo assign no mesmo período NÃO duplica créditos.
 */
final readonly class AssignPlanToAccount
{
    public function __construct(
        private PlanRepository $plans,
        private GrantCredits $grantCredits,
        private TransactionManager $tx,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    public function handle(string $accountId, string $planId): string
    {
        $plan = $this->plans->findById($planId) ?? throw PlanNotFound::withId($planId);

        return $this->tx->transactional(function () use ($accountId, $plan): string {
            $now = $this->clock->now();
            $periodEnd = $plan->billingPeriod === BillingPeriod::Monthly
                ? $now->modify('+30 days')
                : null;

            $subscriptionId = $this->ids->generate();

            DB::table('subscriptions')->insert([
                'id' => $subscriptionId,
                'account_id' => $accountId,
                'plan_id' => $plan->id,
                'price_cents' => $plan->price->cents,
                'currency' => $plan->price->currency,
                'status' => 'active',
                'current_period_start' => $now,
                'current_period_end' => $periodEnd,
                'renews_at' => $periodEnd,
                'cancelled_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($plan->includedCredits > 0) {
                $this->grantCredits->handle(new GrantCreditsInput(
                    accountId: $accountId,
                    amount: $plan->includedCredits,
                    referenceType: 'subscription',
                    referenceId: $subscriptionId,
                    idempotencyKey: "subscription:{$subscriptionId}:initial-grant",
                    metadata: ['plan_id' => $plan->id, 'plan_slug' => $plan->slug],
                ));
            }

            return $subscriptionId;
        });
    }
}

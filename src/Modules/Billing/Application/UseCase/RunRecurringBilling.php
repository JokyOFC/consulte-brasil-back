<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Src\Modules\Billing\Domain\Entity\Invoice;
use Src\Modules\Billing\Domain\Entity\InvoiceItem;
use Src\Modules\Billing\Domain\Repository\InvoiceRepository;
use Src\Modules\Billing\Domain\Repository\SubscriptionRepository;
use Src\Modules\Billing\Domain\ValueObject\InvoiceStatus;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\InvoiceModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\SubscriptionModel;
use Src\Shared\Application\Contracts\Clock;
use Src\Shared\Application\Contracts\IdGenerator;

/**
 * Roda o ciclo de cobrança recorrente:
 *
 *  1. Marca como vencidas (overdue) as faturas em aberto com vencimento passado.
 *  2. Para assinaturas MANUAIS no fim do ciclo, gera a próxima fatura (PIX/boleto).
 *
 * Assinaturas no cartão (Preapproval) são cobradas automaticamente pelo
 * Mercado Pago — o crédito chega via webhook, então não geramos fatura aqui.
 */
final readonly class RunRecurringBilling
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private InvoiceRepository $invoices,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    /** @return array{overdue: int, invoices_generated: int} */
    public function handle(): array
    {
        $now = $this->clock->now();
        $nowStr = $now->format('Y-m-d H:i:s');

        $overdue = InvoiceModel::query()
            ->where('status', InvoiceStatus::Open->value)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $nowStr)
            ->update(['status' => InvoiceStatus::Overdue->value]);

        $generated = 0;

        $due = SubscriptionModel::query()
            ->where('status', 'active')
            ->where('payment_method', 'manual')
            ->whereNotNull('next_billing_at')
            ->where('next_billing_at', '<=', $nowStr)
            ->get();

        foreach ($due as $sub) {
            $plan = PlanModel::query()->find($sub->plan_id);
            if ($plan === null) {
                continue;
            }

            $amount = (int) $plan->price_cents;
            $periodEnd = $now->modify('+30 days');

            $invoice = new Invoice(
                id: $this->ids->generate(),
                accountId: $sub->account_id,
                subscriptionId: $sub->id,
                status: InvoiceStatus::Open,
                amountCents: $amount,
                description: "Plano {$plan->name}",
                dueDate: $now->modify('+3 days'),
                periodStart: $now,
                periodEnd: $periodEnd,
                items: [new InvoiceItem($this->ids->generate(), "Plano {$plan->name}", $amount)],
                createdAt: $now,
            );
            $this->invoices->save($invoice);

            $subscription = $this->subscriptions->findById($sub->id);
            if ($subscription !== null) {
                $subscription->nextBillingAt = $periodEnd;
                $subscription->renewsAt = $periodEnd;
                $subscription->currentPeriodStart = $now;
                $subscription->currentPeriodEnd = $periodEnd;
                $this->subscriptions->save($subscription);
            }

            $generated++;
        }

        return ['overdue' => $overdue, 'invoices_generated' => $generated];
    }
}

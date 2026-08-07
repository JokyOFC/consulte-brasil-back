<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Src\Modules\Billing\Application\Service\InvoiceNumberGenerator;
use Src\Modules\Billing\Domain\Entity\Invoice;
use Src\Modules\Billing\Domain\Entity\InvoiceItem;
use Src\Modules\Billing\Domain\Repository\InvoiceRepository;
use Src\Modules\Billing\Domain\ValueObject\InvoiceStatus;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\InvoiceModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\SubscriptionModel;
use Src\Shared\Application\Contracts\Clock;
use Src\Shared\Application\Contracts\IdGenerator;

/**
 * Garante que cada assinatura manual ativa tenha uma fatura de renovação
 * em aberto (pagamento antecipado). Idempotente: no máximo uma fatura
 * open/overdue por assinatura.
 */
final readonly class EnsureRenewalInvoices
{
    public function __construct(
        private InvoiceRepository $invoices,
        private InvoiceNumberGenerator $invoiceNumbers,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    /**
     * @param  string|null  $accountId  Quando informado, limita à conta.
     * @return int Quantidade de faturas criadas
     */
    public function handle(?string $accountId = null): int
    {
        $now = $this->clock->now();
        $created = 0;

        $subs = SubscriptionModel::query()
            ->where('status', 'active')
            ->where('payment_method', 'manual')
            ->when($accountId !== null, fn ($q) => $q->where('account_id', $accountId))
            ->get();

        foreach ($subs as $sub) {
            // No máximo uma fatura em aberto (pending) por assinatura.
            // Overdue do ciclo anterior não impede a próxima renovação.
            $hasOpen = InvoiceModel::query()
                ->where('subscription_id', $sub->id)
                ->where('status', InvoiceStatus::Open->value)
                ->exists();

            if ($hasOpen) {
                continue;
            }

            $plan = PlanModel::query()->find($sub->plan_id);
            if ($plan === null) {
                continue;
            }

            // Preço congelado na contratação; fallback para o preço do plano
            // em assinaturas antigas sem snapshot.
            $amount = (int) ($sub->price_cents ?? $plan->price_cents);
            $due = $sub->next_billing_at
                ? new \DateTimeImmutable($sub->next_billing_at->toDateTimeString())
                : ($sub->current_period_end
                    ? new \DateTimeImmutable($sub->current_period_end->toDateTimeString())
                    : $now->modify('+30 days'));

            $periodStart = $due;
            $periodEnd = $due->modify('+30 days');

            $invoice = new Invoice(
                id: $this->ids->generate(),
                accountId: $sub->account_id,
                subscriptionId: $sub->id,
                status: InvoiceStatus::Open,
                amountCents: $amount,
                description: "Renovação — Plano {$plan->name}",
                dueDate: $due,
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                items: [new InvoiceItem($this->ids->generate(), "Plano {$plan->name}", $amount)],
                metadata: ['origin' => 'renewal'],
                createdAt: $now,
                number: $this->invoiceNumbers->next(),
            );

            $this->invoices->save($invoice);
            $created++;
        }

        return $created;
    }
}

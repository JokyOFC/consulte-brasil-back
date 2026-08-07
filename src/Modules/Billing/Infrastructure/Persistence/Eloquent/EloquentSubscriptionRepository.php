<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Persistence\Eloquent;

use DateTimeImmutable;
use Src\Modules\Billing\Domain\Entity\Subscription;
use Src\Modules\Billing\Domain\Repository\SubscriptionRepository;
use Src\Modules\Billing\Domain\ValueObject\SubscriptionStatus;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\SubscriptionModel;
use Src\Shared\Domain\ValueObject\Money;

final class EloquentSubscriptionRepository implements SubscriptionRepository
{
    public function save(Subscription $subscription): void
    {
        SubscriptionModel::query()->updateOrCreate(
            ['id' => $subscription->id],
            [
                'account_id' => $subscription->accountId,
                'plan_id' => $subscription->planId,
                'price_cents' => $subscription->price?->cents,
                'currency' => $subscription->price?->currency,
                'status' => $subscription->status->value,
                'mp_preapproval_id' => $subscription->mpPreapprovalId,
                'payment_method' => $subscription->paymentMethod,
                'current_period_start' => $subscription->currentPeriodStart?->format('Y-m-d H:i:s'),
                'current_period_end' => $subscription->currentPeriodEnd?->format('Y-m-d H:i:s'),
                'renews_at' => $subscription->renewsAt?->format('Y-m-d H:i:s'),
                'next_billing_at' => $subscription->nextBillingAt?->format('Y-m-d H:i:s'),
                'cancelled_at' => $subscription->cancelledAt?->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function findById(string $id): ?Subscription
    {
        $model = SubscriptionModel::query()->find($id);

        return $model === null ? null : $this->toEntity($model);
    }

    public function findByMpPreapprovalId(string $mpPreapprovalId): ?Subscription
    {
        $model = SubscriptionModel::query()->where('mp_preapproval_id', $mpPreapprovalId)->first();

        return $model === null ? null : $this->toEntity($model);
    }

    public function findActiveByAccount(string $accountId): ?Subscription
    {
        $model = SubscriptionModel::query()
            ->where('account_id', $accountId)
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
            ->orderByDesc('created_at')
            ->first();

        return $model === null ? null : $this->toEntity($model);
    }

    private function toEntity(SubscriptionModel $m): Subscription
    {
        return new Subscription(
            id: $m->id,
            accountId: $m->account_id,
            planId: $m->plan_id,
            status: SubscriptionStatus::from($m->status),
            mpPreapprovalId: $m->mp_preapproval_id,
            paymentMethod: $m->payment_method,
            currentPeriodStart: $m->current_period_start ? new DateTimeImmutable($m->current_period_start->toDateTimeString()) : null,
            currentPeriodEnd: $m->current_period_end ? new DateTimeImmutable($m->current_period_end->toDateTimeString()) : null,
            renewsAt: $m->renews_at ? new DateTimeImmutable($m->renews_at->toDateTimeString()) : null,
            nextBillingAt: $m->next_billing_at ? new DateTimeImmutable($m->next_billing_at->toDateTimeString()) : null,
            cancelledAt: $m->cancelled_at ? new DateTimeImmutable($m->cancelled_at->toDateTimeString()) : null,
            createdAt: $m->created_at ? new DateTimeImmutable($m->created_at->toDateTimeString()) : null,
            price: $m->price_cents !== null ? Money::of((int) $m->price_cents, (string) ($m->currency ?? 'BRL')) : null,
        );
    }
}

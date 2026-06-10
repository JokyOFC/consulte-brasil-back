<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Repository;

use Src\Modules\Billing\Domain\Entity\Subscription;

interface SubscriptionRepository
{
    public function save(Subscription $subscription): void;

    public function findById(string $id): ?Subscription;

    public function findByMpPreapprovalId(string $mpPreapprovalId): ?Subscription;

    public function findActiveByAccount(string $accountId): ?Subscription;
}

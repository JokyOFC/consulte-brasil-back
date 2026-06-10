<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Repository;

use Src\Modules\Billing\Domain\Entity\Plan;

interface PlanRepository
{
    public function save(Plan $plan): void;

    public function findById(string $id): ?Plan;

    public function findBySlug(string $slug): ?Plan;

    /** @return list<Plan> */
    public function active(): array;
}

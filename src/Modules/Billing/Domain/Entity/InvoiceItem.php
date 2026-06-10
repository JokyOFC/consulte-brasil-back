<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Entity;

final class InvoiceItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $description,
        public readonly int $amountCents,
        public readonly int $quantity = 1,
    ) {}
}

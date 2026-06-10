<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Repository;

use Src\Modules\Billing\Domain\Entity\Invoice;

interface InvoiceRepository
{
    public function save(Invoice $invoice): void;

    public function findById(string $id): ?Invoice;
}

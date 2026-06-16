<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Repository;

use Src\Modules\Billing\Domain\Entity\Payment;

interface PaymentRepository
{
    public function save(Payment $payment): void;

    public function findById(string $id): ?Payment;

    public function findByMpPaymentId(string $mpPaymentId): ?Payment;

    public function findLatestPendingByInvoiceId(string $invoiceId): ?Payment;
}

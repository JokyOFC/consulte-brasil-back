<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\DTO;

use Src\Modules\Billing\Domain\ValueObject\PaymentMethod;

final readonly class PayInvoiceInput
{
    public function __construct(
        public string $accountId,
        public string $invoiceId,
        public PaymentMethod $method,
        public string $payerEmail,
        public ?string $cardToken = null,
        public int $installments = 1,
        public ?string $paymentMethodId = null,
        public ?string $issuerId = null,
    ) {}
}

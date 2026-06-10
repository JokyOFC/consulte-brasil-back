<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\ValueObject;

enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Canceled = 'canceled';
}

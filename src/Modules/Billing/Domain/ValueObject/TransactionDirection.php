<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\ValueObject;

enum TransactionDirection: string
{
    case Credit = 'credit'; // entra crédito (aumenta saldo/disponível)
    case Debit = 'debit';   // sai crédito (reduz saldo/disponível)
}

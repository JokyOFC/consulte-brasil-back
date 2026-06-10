<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\ValueObject;

enum PaymentMethod: string
{
    case Pix = 'pix';
    case CreditCard = 'credit_card';
    case Boleto = 'boleto';
}

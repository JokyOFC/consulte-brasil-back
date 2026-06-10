<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\ValueObject;

enum PaymentType: string
{
    case Topup = 'topup';      // recarga avulsa de saldo
    case Invoice = 'invoice';  // pagamento de fatura (plano)
}

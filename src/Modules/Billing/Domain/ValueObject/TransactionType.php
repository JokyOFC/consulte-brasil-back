<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\ValueObject;

enum TransactionType: string
{
    case Grant = 'grant';        // concessão (compra/plano)
    case Reserve = 'reserve';    // bloqueio durante a consulta
    case Commit = 'commit';      // consumo confirmado da reserva
    case Refund = 'refund';      // estorno da reserva (falha do provedor)
    case Expire = 'expire';      // expiração de créditos/reserva órfã
    case Adjustment = 'adjustment'; // ajuste manual do admin
}

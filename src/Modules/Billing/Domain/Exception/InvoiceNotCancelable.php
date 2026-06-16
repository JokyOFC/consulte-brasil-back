<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Exception;

use DateTimeImmutable;
use Src\Shared\Domain\Exception\DomainException;

final class InvoiceNotCancelable extends DomainException
{
    public static function pendingPayment(DateTimeImmutable $cancelableAt): self
    {
        return new self(
            'Aguarde a confirmação do pagamento ou tente cancelar após '
            .$cancelableAt->format('H:i')
            .'.',
        );
    }

    public static function alreadyPaid(): self
    {
        return new self('Esta fatura já foi paga.');
    }
}

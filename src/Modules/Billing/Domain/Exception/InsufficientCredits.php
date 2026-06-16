<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Exception;

use Src\Modules\Billing\Domain\ValueObject\WalletId;
use Src\Shared\Domain\Exception\DomainException;
use Src\Shared\Domain\ValueObject\CreditAmount;

final class InsufficientCredits extends DomainException
{
    public static function forWallet(WalletId $walletId, CreditAmount $available, CreditAmount $requested): self
    {
        return new self(sprintf(
            'Saldo insuficiente na carteira [%s]: disponível %d, necessário %d.',
            $walletId->value,
            $available->value,
            $requested->value,
        ));
    }
}

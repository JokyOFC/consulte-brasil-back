<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Exception;

use Src\Shared\Domain\Exception\DomainException;

final class WalletNotFound extends DomainException
{
    public static function forAccount(string $accountId): self
    {
        return new self("No wallet found for account [{$accountId}].");
    }
}

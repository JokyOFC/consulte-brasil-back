<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Listeners;

use Src\Modules\Billing\Application\UseCase\CreateWallet;
use Src\Modules\Identity\Domain\Event\AccountRegistered;

/**
 * Integração Identity → Billing: ao registrar uma conta, provisiona sua
 * carteira de créditos. O Identity não conhece o Billing; o acoplamento é
 * apenas pelo contrato do evento.
 */
final readonly class ProvisionWalletForAccount
{
    public function __construct(private CreateWallet $createWallet) {}

    public function handle(AccountRegistered $event): void
    {
        $this->createWallet->handle($event->accountId);
    }
}

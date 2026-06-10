<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Src\Modules\Billing\Domain\Entity\Wallet;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Billing\Domain\ValueObject\WalletId;
use Src\Shared\Application\Contracts\IdGenerator;
use Src\Shared\Domain\ValueObject\CreditAmount;

final readonly class CreateWallet
{
    public function __construct(
        private WalletRepository $wallets,
        private IdGenerator $ids,
    ) {}

    /** Idempotente: devolve a carteira existente, se já houver. */
    public function handle(string $accountId): Wallet
    {
        $existing = $this->wallets->findByAccountId($accountId);

        if ($existing !== null) {
            return $existing;
        }

        $wallet = new Wallet(
            new WalletId($this->ids->generate()),
            $accountId,
            CreditAmount::zero(),
            CreditAmount::zero(),
        );

        $this->wallets->save($wallet);

        return $wallet;
    }
}

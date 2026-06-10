<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Src\Modules\Billing\Application\DTO\GrantCreditsInput;
use Src\Modules\Billing\Application\Port\CreditBalanceCache;
use Src\Modules\Billing\Domain\Entity\CreditTransaction;
use Src\Modules\Billing\Domain\Exception\WalletNotFound;
use Src\Modules\Billing\Domain\Repository\CreditTransactionRepository;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Billing\Domain\ValueObject\TransactionDirection;
use Src\Modules\Billing\Domain\ValueObject\TransactionType;
use Src\Shared\Application\Contracts\Clock;
use Src\Shared\Application\Contracts\IdGenerator;
use Src\Shared\Application\Contracts\TransactionManager;
use Src\Shared\Domain\ValueObject\CreditAmount;

/**
 * Credita a carteira (compra de pacote, concessão de plano, bônus).
 * Idempotente via idempotencyKey.
 */
final readonly class GrantCredits
{
    public function __construct(
        private WalletRepository $wallets,
        private CreditTransactionRepository $ledger,
        private TransactionManager $tx,
        private CreditBalanceCache $cache,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    public function handle(GrantCreditsInput $input): void
    {
        $applied = $this->tx->transactional(function () use ($input): bool {
            if ($input->idempotencyKey !== null
                && $this->ledger->findByIdempotencyKey($input->idempotencyKey) !== null) {
                return false;
            }

            $wallet = $this->wallets->findByAccountIdForUpdate($input->accountId)
                ?? throw WalletNotFound::forAccount($input->accountId);

            $amount = CreditAmount::of($input->amount);
            $wallet->grant($amount);
            $this->wallets->save($wallet);

            $this->ledger->append(new CreditTransaction(
                id: $this->ids->generate(),
                accountId: $input->accountId,
                walletId: $wallet->id,
                type: TransactionType::Grant,
                direction: TransactionDirection::Credit,
                amount: $amount,
                balanceAfter: $wallet->balance(),
                referenceType: $input->referenceType,
                referenceId: $input->referenceId,
                idempotencyKey: $input->idempotencyKey,
                metadata: $input->metadata,
                createdAt: $this->clock->now(),
            ));

            return true;
        });

        if ($applied) {
            $this->cache->increment($input->accountId, $input->amount);
        }
    }
}

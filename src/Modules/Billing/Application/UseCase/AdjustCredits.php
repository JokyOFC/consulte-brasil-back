<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

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
 * Ajuste manual do admin no saldo (estornos, brindes, correções).
 *
 *   $delta > 0 → credita; $delta < 0 → debita (limitado pelo saldo disponível).
 *
 * Sempre lança no ledger com type=adjustment, capturando `reason` e
 * `performedBy` para auditoria. Idempotente via TransactionManager + lock
 * de linha.
 */
final readonly class AdjustCredits
{
    public function __construct(
        private WalletRepository $wallets,
        private CreditTransactionRepository $ledger,
        private TransactionManager $tx,
        private CreditBalanceCache $cache,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    public function handle(string $accountId, int $delta, string $reason, ?string $performedBy = null): int
    {
        if ($delta === 0) {
            return 0;
        }

        $applied = $this->tx->transactional(function () use ($accountId, $delta, $reason, $performedBy): int {
            $wallet = $this->wallets->findByAccountIdForUpdate($accountId)
                ?? throw WalletNotFound::forAccount($accountId);

            $applied = $wallet->adjust($delta);
            if ($applied === 0) {
                return 0;
            }
            $this->wallets->save($wallet);

            $this->ledger->append(new CreditTransaction(
                id: $this->ids->generate(),
                accountId: $accountId,
                walletId: $wallet->id,
                type: TransactionType::Adjustment,
                direction: $applied > 0 ? TransactionDirection::Credit : TransactionDirection::Debit,
                amount: CreditAmount::of(abs($applied)),
                balanceAfter: $wallet->balance(),
                metadata: ['reason' => $reason, 'performed_by' => $performedBy],
                createdAt: $this->clock->now(),
            ));

            return $applied;
        });

        if ($applied > 0) {
            $this->cache->increment($accountId, $applied);
        } elseif ($applied < 0) {
            $this->cache->decrement($accountId, abs($applied));
        }

        return $applied;
    }
}

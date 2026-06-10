<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Src\Modules\Billing\Application\Port\CreditBalanceCache;
use Src\Modules\Billing\Domain\Entity\CreditTransaction;
use Src\Modules\Billing\Domain\Exception\ReservationNotFound;
use Src\Modules\Billing\Domain\Exception\WalletNotFound;
use Src\Modules\Billing\Domain\Repository\CreditTransactionRepository;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Billing\Domain\ValueObject\ReservationId;
use Src\Modules\Billing\Domain\ValueObject\TransactionDirection;
use Src\Modules\Billing\Domain\ValueObject\TransactionType;
use Src\Shared\Application\Contracts\Clock;
use Src\Shared\Application\Contracts\IdGenerator;
use Src\Shared\Application\Contracts\TransactionManager;

/**
 * Etapa 2b: consulta falhou (todos os provedores) → libera a reserva.
 * O cliente NÃO é cobrado. Idempotente e devolve o "disponível" ao cache.
 */
final readonly class RefundCredits
{
    public function __construct(
        private WalletRepository $wallets,
        private CreditTransactionRepository $ledger,
        private TransactionManager $tx,
        private CreditBalanceCache $cache,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    public function handle(string $reservationId): void
    {
        $refunded = $this->tx->transactional(function () use ($reservationId): ?array {
            $id = new ReservationId($reservationId);

            if ($this->ledger->hasSettlement($id)) {
                return null;
            }

            $reserve = $this->ledger->findReserve($id)
                ?? throw ReservationNotFound::withId($reservationId);

            $wallet = $this->wallets->findByAccountIdForUpdate($reserve->accountId)
                ?? throw WalletNotFound::forAccount($reserve->accountId);

            $wallet->refund($reserve->amount);
            $this->wallets->save($wallet);

            $this->ledger->append(new CreditTransaction(
                id: $this->ids->generate(),
                accountId: $reserve->accountId,
                walletId: $wallet->id,
                type: TransactionType::Refund,
                direction: TransactionDirection::Credit,
                amount: $reserve->amount,
                balanceAfter: $wallet->balance(),
                reservationId: $id,
                referenceType: $reserve->referenceType,
                referenceId: $reserve->referenceId,
                createdAt: $this->clock->now(),
            ));

            return ['accountId' => $reserve->accountId, 'amount' => $reserve->amount->value];
        });

        if ($refunded !== null) {
            $this->cache->increment($refunded['accountId'], $refunded['amount']);
        }
    }
}

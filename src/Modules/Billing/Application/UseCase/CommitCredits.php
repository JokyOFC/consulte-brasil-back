<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

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
 * Etapa 2a: consulta teve sucesso → consome a reserva (vira débito real).
 * Idempotente: se a reserva já foi liquidada (commit/refund), não faz nada.
 * Não altera o "disponível" — ele já caiu na reserva.
 */
final readonly class CommitCredits
{
    public function __construct(
        private WalletRepository $wallets,
        private CreditTransactionRepository $ledger,
        private TransactionManager $tx,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    public function handle(string $reservationId): void
    {
        $this->tx->transactional(function () use ($reservationId): void {
            $id = new ReservationId($reservationId);

            if ($this->ledger->hasSettlement($id)) {
                return;
            }

            $reserve = $this->ledger->findReserve($id)
                ?? throw ReservationNotFound::withId($reservationId);

            $wallet = $this->wallets->findByAccountIdForUpdate($reserve->accountId)
                ?? throw WalletNotFound::forAccount($reserve->accountId);

            $wallet->commit($reserve->amount);
            $this->wallets->save($wallet);

            $this->ledger->append(new CreditTransaction(
                id: $this->ids->generate(),
                accountId: $reserve->accountId,
                walletId: $wallet->id,
                type: TransactionType::Commit,
                direction: TransactionDirection::Debit,
                amount: $reserve->amount,
                balanceAfter: $wallet->balance(),
                reservationId: $id,
                referenceType: $reserve->referenceType,
                referenceId: $reserve->referenceId,
                createdAt: $this->clock->now(),
            ));
        });
    }
}

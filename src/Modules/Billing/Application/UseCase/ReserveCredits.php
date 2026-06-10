<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Src\Modules\Billing\Application\DTO\ReservationResult;
use Src\Modules\Billing\Application\DTO\ReserveCreditsInput;
use Src\Modules\Billing\Application\Port\CreditBalanceCache;
use Src\Modules\Billing\Domain\Entity\CreditTransaction;
use Src\Modules\Billing\Domain\Exception\WalletNotFound;
use Src\Modules\Billing\Domain\Repository\CreditTransactionRepository;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Billing\Domain\ValueObject\ReservationId;
use Src\Modules\Billing\Domain\ValueObject\TransactionDirection;
use Src\Modules\Billing\Domain\ValueObject\TransactionType;
use Src\Shared\Application\Contracts\Clock;
use Src\Shared\Application\Contracts\IdGenerator;
use Src\Shared\Application\Contracts\TransactionManager;
use Src\Shared\Domain\ValueObject\CreditAmount;

/**
 * Etapa 1 do fluxo de cobrança: bloqueia créditos antes da consulta.
 *
 * A reserva é serializada por lock de linha (findByAccountIdForUpdate)
 * dentro da transação — duas requisições concorrentes nunca reservam além
 * do disponível. Lança InsufficientCredits (→ HTTP 402) se faltar saldo.
 */
final readonly class ReserveCredits
{
    public function __construct(
        private WalletRepository $wallets,
        private CreditTransactionRepository $ledger,
        private TransactionManager $tx,
        private CreditBalanceCache $cache,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    public function handle(ReserveCreditsInput $input): ReservationResult
    {
        $newlyReserved = false;

        $result = $this->tx->transactional(function () use ($input, &$newlyReserved): ReservationResult {
            // Replay idempotente: devolve a reserva já criada.
            if ($input->idempotencyKey !== null) {
                $existing = $this->ledger->findByIdempotencyKey($input->idempotencyKey);
                if ($existing !== null && $existing->reservationId !== null) {
                    $wallet = $this->wallets->findByAccountId($input->accountId);

                    return new ReservationResult(
                        $existing->reservationId->value,
                        $wallet?->available()->value ?? 0,
                    );
                }
            }

            $wallet = $this->wallets->findByAccountIdForUpdate($input->accountId)
                ?? throw WalletNotFound::forAccount($input->accountId);

            $amount = CreditAmount::of($input->amount);
            $wallet->reserve($amount); // lança InsufficientCredits
            $this->wallets->save($wallet);

            $reservationId = new ReservationId($this->ids->generate());

            $this->ledger->append(new CreditTransaction(
                id: $this->ids->generate(),
                accountId: $input->accountId,
                walletId: $wallet->id,
                type: TransactionType::Reserve,
                direction: TransactionDirection::Debit,
                amount: $amount,
                balanceAfter: $wallet->balance(),
                reservationId: $reservationId,
                referenceType: $input->referenceType,
                referenceId: $input->referenceId,
                idempotencyKey: $input->idempotencyKey,
                metadata: $input->metadata,
                createdAt: $this->clock->now(),
            ));

            $newlyReserved = true;

            return new ReservationResult($reservationId->value, $wallet->available()->value);
        });

        if ($newlyReserved) {
            $this->cache->decrement($input->accountId, $input->amount);
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Entity;

use Src\Modules\Billing\Domain\Exception\InsufficientCredits;
use Src\Modules\Billing\Domain\ValueObject\WalletId;
use Src\Shared\Domain\ValueObject\CreditAmount;

/**
 * Carteira de créditos de uma conta. Projeção transacional do ledger:
 * a verdade é a soma dos lançamentos, mas a carteira mantém os totais
 * correntes e GUARDA os invariantes do saldo.
 *
 *   disponível = saldo - reservado   (sempre >= 0)
 *
 * As mutações são sempre persistidas dentro de uma transação com lock de
 * linha (ver EloquentWalletRepository::findByAccountIdForUpdate), o que
 * torna a reserva atômica e à prova de concorrência.
 */
final class Wallet
{
    public function __construct(
        public readonly WalletId $id,
        public readonly string $accountId,
        private CreditAmount $balance,
        private CreditAmount $reserved,
    ) {}

    public function balance(): CreditAmount
    {
        return $this->balance;
    }

    public function reserved(): CreditAmount
    {
        return $this->reserved;
    }

    public function available(): CreditAmount
    {
        return $this->balance->subtract($this->reserved);
    }

    public function grant(CreditAmount $amount): void
    {
        $this->balance = $this->balance->add($amount);
    }

    /** @throws InsufficientCredits quando não há disponível suficiente. */
    public function reserve(CreditAmount $amount): void
    {
        if ($this->available()->isLessThan($amount)) {
            throw InsufficientCredits::forWallet($this->id, $this->available(), $amount);
        }

        $this->reserved = $this->reserved->add($amount);
    }

    /** Consome a reserva: sai do reservado e do saldo. */
    public function commit(CreditAmount $amount): void
    {
        $this->reserved = $this->reserved->subtract($amount);
        $this->balance = $this->balance->subtract($amount);
    }

    /** Libera a reserva sem consumir saldo. */
    public function refund(CreditAmount $amount): void
    {
        $this->reserved = $this->reserved->subtract($amount);
    }

    /**
     * Ajuste manual (admin). Positivo concede; negativo remove.
     * Para débitos, nunca leva o saldo abaixo de "reservado" (preserva o
     * invariante balance ≥ reserved) — retorna o delta efetivamente aplicado.
     */
    public function adjust(int $delta): int
    {
        if ($delta > 0) {
            $this->balance = $this->balance->add(CreditAmount::of($delta));

            return $delta;
        }

        $reservedFloor = $this->reserved->value;
        $newBalance = max($reservedFloor, $this->balance->value + $delta);
        $applied = $newBalance - $this->balance->value; // <= 0
        $this->balance = CreditAmount::of($newBalance);

        return $applied;
    }
}

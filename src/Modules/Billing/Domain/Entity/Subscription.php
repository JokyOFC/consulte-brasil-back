<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\Entity;

use DateTimeImmutable;
use Src\Modules\Billing\Domain\ValueObject\SubscriptionStatus;
use Src\Shared\Domain\ValueObject\Money;

/**
 * Assinatura de um plano por uma conta. No modelo de carteira em dinheiro,
 * cada ciclo gera uma recarga de saldo (via fatura paga ou cobrança
 * automática no cartão pelo Mercado Pago).
 *
 * O preço é congelado na contratação (snapshot): mudanças posteriores no
 * preço do plano não afetam a assinatura, salvo repreçamento explícito.
 */
final class Subscription
{
    public function __construct(
        public readonly string $id,
        public readonly string $accountId,
        public string $planId,
        public SubscriptionStatus $status,
        public ?string $mpPreapprovalId = null,
        public ?string $paymentMethod = null,
        public ?DateTimeImmutable $currentPeriodStart = null,
        public ?DateTimeImmutable $currentPeriodEnd = null,
        public ?DateTimeImmutable $renewsAt = null,
        public ?DateTimeImmutable $nextBillingAt = null,
        public ?DateTimeImmutable $cancelledAt = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?Money $price = null,
    ) {}

    public function cancel(?DateTimeImmutable $at = null): void
    {
        $this->status = SubscriptionStatus::Canceled;
        $this->cancelledAt = $at ?? new DateTimeImmutable;
        $this->renewsAt = null;
        $this->nextBillingAt = null;
    }

    public function markPastDue(): void
    {
        $this->status = SubscriptionStatus::PastDue;
    }

    public function reactivate(): void
    {
        $this->status = SubscriptionStatus::Active;
    }
}

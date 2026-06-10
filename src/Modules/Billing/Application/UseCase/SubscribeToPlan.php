<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Src\Modules\Billing\Application\DTO\PayInvoiceInput;
use Src\Modules\Billing\Application\DTO\SubscribeToPlanInput;
use Src\Modules\Billing\Application\Gateway\GatewayPreapprovalInput;
use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Domain\Entity\Invoice;
use Src\Modules\Billing\Domain\Entity\InvoiceItem;
use Src\Modules\Billing\Domain\Entity\Subscription;
use Src\Modules\Billing\Domain\Exception\PlanNotFound;
use Src\Modules\Billing\Domain\Repository\InvoiceRepository;
use Src\Modules\Billing\Domain\Repository\PlanRepository;
use Src\Modules\Billing\Domain\Repository\SubscriptionRepository;
use Src\Modules\Billing\Domain\ValueObject\InvoiceStatus;
use Src\Modules\Billing\Domain\ValueObject\PaymentMethod;
use Src\Modules\Billing\Domain\ValueObject\SubscriptionStatus;
use Src\Shared\Application\Contracts\Clock;
use Src\Shared\Application\Contracts\IdGenerator;

/**
 * Assina um plano (recarga recorrente mensal). Modelo híbrido:
 *
 *  - Cartão (com token): cria Preapproval no Mercado Pago → cobrança
 *    automática a cada ciclo (creditada na carteira via webhook).
 *  - PIX/boleto: cria a assinatura "manual" + a primeira fatura, devolvendo
 *    a cobrança (QR/boleto) para o cliente pagar. Próximos ciclos geram
 *    novas faturas (command billing:run-recurring).
 *
 * @phpstan-type SubscribeResult array{subscription: Subscription, invoice?: Invoice, payment?: \Src\Modules\Billing\Domain\Entity\Payment}
 */
final readonly class SubscribeToPlan
{
    public function __construct(
        private PlanRepository $plans,
        private SubscriptionRepository $subscriptions,
        private InvoiceRepository $invoices,
        private PaymentGateway $gateway,
        private PayInvoice $payInvoice,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    /** @return SubscribeResult */
    public function handle(SubscribeToPlanInput $input): array
    {
        $plan = $this->plans->findById($input->planId) ?? throw PlanNotFound::withId($input->planId);

        $now = $this->clock->now();
        $next = $now->modify('+30 days');
        $subscriptionId = $this->ids->generate();
        $amountCents = $plan->price->cents;

        if ($input->method === PaymentMethod::CreditCard && $input->cardToken !== null) {
            $preapproval = $this->gateway->createPreapproval(new GatewayPreapprovalInput(
                amountCents: $amountCents,
                reason: "Assinatura {$plan->name}",
                payerEmail: $input->payerEmail,
                externalReference: "subscription:{$subscriptionId}",
                backUrl: $input->backUrl,
                cardTokenId: $input->cardToken,
            ));

            $subscription = new Subscription(
                id: $subscriptionId,
                accountId: $input->accountId,
                planId: $plan->id,
                status: SubscriptionStatus::Active,
                mpPreapprovalId: $preapproval->mpPreapprovalId,
                paymentMethod: 'credit_card',
                currentPeriodStart: $now,
                currentPeriodEnd: $next,
                renewsAt: $next,
                nextBillingAt: $next,
                createdAt: $now,
            );
            $this->subscriptions->save($subscription);

            return ['subscription' => $subscription];
        }

        // Fluxo manual (PIX/boleto): assinatura + primeira fatura.
        $subscription = new Subscription(
            id: $subscriptionId,
            accountId: $input->accountId,
            planId: $plan->id,
            status: SubscriptionStatus::Active,
            paymentMethod: 'manual',
            currentPeriodStart: $now,
            currentPeriodEnd: $next,
            renewsAt: $next,
            nextBillingAt: $next,
            createdAt: $now,
        );
        $this->subscriptions->save($subscription);

        $invoice = new Invoice(
            id: $this->ids->generate(),
            accountId: $input->accountId,
            subscriptionId: $subscriptionId,
            status: InvoiceStatus::Open,
            amountCents: $amountCents,
            description: "Plano {$plan->name}",
            dueDate: $now,
            periodStart: $now,
            periodEnd: $next,
            items: [new InvoiceItem($this->ids->generate(), "Plano {$plan->name}", $amountCents)],
            createdAt: $now,
        );
        $this->invoices->save($invoice);

        $payment = $this->payInvoice->handle(new PayInvoiceInput(
            invoiceId: $invoice->id,
            method: $input->method,
            payerEmail: $input->payerEmail,
        ));

        return ['subscription' => $subscription, 'invoice' => $invoice, 'payment' => $payment];
    }
}

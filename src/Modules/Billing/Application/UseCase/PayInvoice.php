<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Src\Modules\Billing\Application\DTO\PayInvoiceInput;
use Src\Modules\Billing\Application\Gateway\GatewayCharge;
use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Application\Service\PayerResolver;
use Src\Modules\Billing\Domain\Entity\Invoice;
use Src\Modules\Billing\Domain\Entity\Payment;
use Src\Modules\Billing\Domain\Exception\InvoiceNotFound;
use Src\Modules\Billing\Domain\Repository\InvoiceRepository;
use Src\Modules\Billing\Domain\Repository\PaymentRepository;
use Src\Modules\Billing\Domain\ValueObject\PaymentMethod;
use Src\Modules\Billing\Domain\ValueObject\PaymentStatus;
use Src\Modules\Billing\Domain\ValueObject\PaymentType;
use Src\Shared\Application\Contracts\Clock;
use Src\Shared\Application\Contracts\IdGenerator;

/**
 * Gera a cobrança (PIX/boleto/cartão) para pagar uma fatura em aberto.
 */
final readonly class PayInvoice
{
    public function __construct(
        private PaymentGateway $gateway,
        private PaymentRepository $payments,
        private InvoiceRepository $invoices,
        private PayerResolver $payer,
        private SettleApprovedPayment $settle,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    public function handle(PayInvoiceInput $input): Payment
    {
        $invoice = $this->invoices->findById($input->invoiceId);

        if ($invoice === null || ! $invoice->isPayable()) {
            throw InvoiceNotFound::withId($input->invoiceId);
        }

        $paymentId = $this->ids->generate();

        $chargeInput = $this->payer->build(
            accountId: $invoice->accountId,
            amountCents: $invoice->amountCents,
            description: $invoice->description ?? 'Fatura',
            externalReference: "payment:{$paymentId}",
            payerEmail: $input->payerEmail,
            card: [
                'token' => $input->cardToken,
                'installments' => $input->installments,
                'payment_method_id' => $input->paymentMethodId,
                'issuer_id' => $input->issuerId,
            ],
        );

        $charge = match ($input->method) {
            PaymentMethod::Pix => $this->gateway->createPixPayment($chargeInput),
            PaymentMethod::Boleto => $this->gateway->createBoletoPayment($chargeInput),
            PaymentMethod::CreditCard => $this->gateway->createCardPayment($chargeInput),
        };

        $payment = $this->buildPayment($paymentId, $invoice, $input->method, $charge);
        $this->payments->save($payment);

        if ($payment->status === PaymentStatus::Approved) {
            $this->settle->handle($payment);
        }

        return $payment;
    }

    private function buildPayment(string $paymentId, Invoice $invoice, PaymentMethod $method, GatewayCharge $charge): Payment
    {
        return new Payment(
            id: $paymentId,
            accountId: $invoice->accountId,
            type: PaymentType::Invoice,
            method: $method,
            status: PaymentStatus::fromMercadoPago($charge->status),
            amountCents: $invoice->amountCents,
            currency: 'BRL',
            invoiceId: $invoice->id,
            mpPaymentId: $charge->mpPaymentId,
            qrCode: $charge->qrCode,
            qrCodeBase64: $charge->qrCodeBase64,
            ticketUrl: $charge->ticketUrl,
            barcode: $charge->barcode,
            description: $invoice->description ?? 'Fatura',
            expiresAt: $charge->expiresAt,
            createdAt: $this->clock->now(),
        );
    }
}

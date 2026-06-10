<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Src\Modules\Billing\Application\DTO\CreateWalletTopupInput;
use Src\Modules\Billing\Application\Gateway\GatewayCharge;
use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Application\Service\PayerResolver;
use Src\Modules\Billing\Domain\Entity\Payment;
use Src\Modules\Billing\Domain\Repository\PaymentRepository;
use Src\Modules\Billing\Domain\ValueObject\PaymentMethod;
use Src\Modules\Billing\Domain\ValueObject\PaymentStatus;
use Src\Modules\Billing\Domain\ValueObject\PaymentType;
use Src\Shared\Application\Contracts\Clock;
use Src\Shared\Application\Contracts\IdGenerator;

/**
 * Recarga avulsa de saldo da carteira via Mercado Pago (PIX/boleto/cartão).
 * Cria o Payment local, gera a cobrança no gateway e — no caso de cartão
 * aprovado na hora — já deposita o saldo (idempotente).
 */
final readonly class CreateWalletTopup
{
    public function __construct(
        private PaymentGateway $gateway,
        private PaymentRepository $payments,
        private PayerResolver $payer,
        private SettleApprovedPayment $settle,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    public function handle(CreateWalletTopupInput $input): Payment
    {
        $paymentId = $this->ids->generate();
        $description = 'Recarga de saldo';

        $chargeInput = $this->payer->build(
            accountId: $input->accountId,
            amountCents: $input->amountCents,
            description: $description,
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

        $payment = $this->buildPayment($paymentId, $input, $description, $charge);
        $this->payments->save($payment);

        if ($payment->status === PaymentStatus::Approved) {
            $this->settle->handle($payment);
        }

        return $payment;
    }

    private function buildPayment(
        string $paymentId,
        CreateWalletTopupInput $input,
        string $description,
        GatewayCharge $charge,
    ): Payment {
        return new Payment(
            id: $paymentId,
            accountId: $input->accountId,
            type: PaymentType::Topup,
            method: $input->method,
            status: PaymentStatus::fromMercadoPago($charge->status),
            amountCents: $input->amountCents,
            currency: 'BRL',
            invoiceId: null,
            mpPaymentId: $charge->mpPaymentId,
            qrCode: $charge->qrCode,
            qrCodeBase64: $charge->qrCodeBase64,
            ticketUrl: $charge->ticketUrl,
            barcode: $charge->barcode,
            description: $description,
            expiresAt: $charge->expiresAt,
            createdAt: $this->clock->now(),
        );
    }
}

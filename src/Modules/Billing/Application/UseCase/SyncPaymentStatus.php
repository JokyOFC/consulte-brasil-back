<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\UseCase;

use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Domain\Entity\Payment;
use Src\Modules\Billing\Domain\Exception\PaymentNotFound;
use Src\Modules\Billing\Domain\Repository\PaymentRepository;
use Src\Modules\Billing\Domain\ValueObject\PaymentStatus;

/**
 * Reconsulta o status de um pagamento no Mercado Pago e liquida quando
 * aprovado. Usado pelo polling do front e antes de cancelar faturas.
 */
final readonly class SyncPaymentStatus
{
    public function __construct(
        private PaymentRepository $payments,
        private PaymentGateway $gateway,
        private SettleApprovedPayment $settle,
    ) {}

    public function handle(string $paymentId, ?string $accountId = null): Payment
    {
        $payment = $this->payments->findById($paymentId);

        if ($payment === null || ($accountId !== null && $payment->accountId !== $accountId)) {
            throw PaymentNotFound::withId($paymentId);
        }

        return $this->sync($payment);
    }

    public function sync(Payment $payment): Payment
    {
        if ($payment->status->isFinal() || $payment->mpPaymentId === null) {
            return $payment;
        }

        $remote = $this->gateway->getPayment($payment->mpPaymentId);
        $mpStatus = PaymentStatus::fromMercadoPago($remote->status);

        if ($mpStatus !== $payment->status) {
            $payment->updateStatus($mpStatus);
            $this->payments->save($payment);
        }

        if ($mpStatus === PaymentStatus::Approved && $payment->paidAt === null) {
            $this->settle->handle($payment);

            return $this->payments->findById($payment->id) ?? $payment;
        }

        return $payment;
    }
}

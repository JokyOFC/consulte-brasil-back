<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use Src\Modules\Billing\Application\Gateway\GatewayCharge;
use Src\Modules\Billing\Application\Gateway\GatewayChargeInput;
use Src\Modules\Billing\Application\Gateway\GatewayPaymentStatus;
use Src\Modules\Billing\Application\Gateway\GatewayPreapproval;
use Src\Modules\Billing\Application\Gateway\GatewayPreapprovalInput;
use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Domain\Exception\PaymentGatewayError;

/**
 * Gateway de pagamentos em memória para testes — substitui o Mercado Pago.
 */
final class FakePaymentGateway implements PaymentGateway
{
    /** @var list<array{method: string, input: GatewayChargeInput, id: string}> */
    public array $charges = [];

    /** Status retornado nas cobranças recém-criadas (pix/boleto = pending). */
    public string $nextChargeStatus = 'pending';

    /** @var array<string, GatewayPaymentStatus> mpPaymentId => status reconsultado */
    public array $remoteStatuses = [];

    public int $cancelledPreapprovals = 0;

    /** @var list<array{id: string, amount_cents: int}> */
    public array $updatedPreapprovals = [];

    /** Simula falha do MP ao repreçar Preapprovals. */
    public bool $failPreapprovalUpdates = false;

    /** @var list<string> */
    public array $cancelledPayments = [];

    public bool $automaticCardRecurringEnabled = true;

    private int $seq = 0;

    public function createPixPayment(GatewayChargeInput $input): GatewayCharge
    {
        return $this->charge('pix', $input);
    }

    public function createBoletoPayment(GatewayChargeInput $input): GatewayCharge
    {
        return $this->charge('boleto', $input);
    }

    public function createCardPayment(GatewayChargeInput $input): GatewayCharge
    {
        return $this->charge('credit_card', $input);
    }

    public function createPreapproval(GatewayPreapprovalInput $input): GatewayPreapproval
    {
        return new GatewayPreapproval('pre_'.(++$this->seq), 'authorized', 'https://mp/init');
    }

    public function cancelPreapproval(string $preapprovalId): void
    {
        $this->cancelledPreapprovals++;
    }

    public function updatePreapprovalAmount(string $preapprovalId, int $amountCents): void
    {
        if ($this->failPreapprovalUpdates) {
            throw PaymentGatewayError::from('preapproval update failed');
        }

        $this->updatedPreapprovals[] = ['id' => $preapprovalId, 'amount_cents' => $amountCents];
    }

    public function getPayment(string $mpPaymentId): GatewayPaymentStatus
    {
        if (isset($this->remoteStatuses[$mpPaymentId])) {
            return $this->remoteStatuses[$mpPaymentId];
        }

        foreach ($this->charges as $charge) {
            if ($charge['id'] !== $mpPaymentId) {
                continue;
            }

            $status = $charge['method'] === 'credit_card' ? 'approved' : $this->nextChargeStatus;

            return new GatewayPaymentStatus(
                mpPaymentId: $mpPaymentId,
                status: $status,
                amountCents: $charge['input']->amountCents,
                externalReference: $charge['input']->externalReference,
            );
        }

        return new GatewayPaymentStatus($mpPaymentId, 'approved', 0, null);
    }

    public function cancelPayment(string $mpPaymentId): void
    {
        $this->cancelledPayments[] = $mpPaymentId;
    }

    public function publicKey(): ?string
    {
        return 'TEST-PUBLIC-KEY';
    }

    public function supportsAutomaticCardRecurring(): bool
    {
        return $this->automaticCardRecurringEnabled;
    }

    /** Registra como aprovado no MP o pagamento da cobrança nº $index (0-based). */
    public function approveCharge(int $index, ?string $externalReference = null): string
    {
        $charge = $this->charges[$index];
        $id = $charge['id'];
        $this->remoteStatuses[$id] = new GatewayPaymentStatus(
            mpPaymentId: $id,
            status: 'approved',
            amountCents: $charge['input']->amountCents,
            externalReference: $externalReference ?? $charge['input']->externalReference,
        );

        return $id;
    }

    private function charge(string $method, GatewayChargeInput $input): GatewayCharge
    {
        $id = 'mp_'.(++$this->seq);
        $this->charges[] = ['method' => $method, 'input' => $input, 'id' => $id];

        $status = $method === 'credit_card' ? 'approved' : $this->nextChargeStatus;

        return new GatewayCharge(
            mpPaymentId: $id,
            status: $status,
            qrCode: 'pix-copia-e-cola',
            qrCodeBase64: base64_encode('qr'),
            ticketUrl: 'https://mp/boleto/'.$id,
            barcode: '00190000090123456789',
        );
    }
}

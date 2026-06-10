<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use Src\Modules\Billing\Application\Gateway\GatewayCharge;
use Src\Modules\Billing\Application\Gateway\GatewayChargeInput;
use Src\Modules\Billing\Application\Gateway\GatewayPaymentStatus;
use Src\Modules\Billing\Application\Gateway\GatewayPreapproval;
use Src\Modules\Billing\Application\Gateway\GatewayPreapprovalInput;
use Src\Modules\Billing\Application\Port\PaymentGateway;

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

    public function getPayment(string $mpPaymentId): GatewayPaymentStatus
    {
        return $this->remoteStatuses[$mpPaymentId]
            ?? new GatewayPaymentStatus($mpPaymentId, 'approved', 0, null);
    }

    public function publicKey(): ?string
    {
        return 'TEST-PUBLIC-KEY';
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

<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\Port;

use Src\Modules\Billing\Application\Gateway\GatewayCharge;
use Src\Modules\Billing\Application\Gateway\GatewayChargeInput;
use Src\Modules\Billing\Application\Gateway\GatewayPaymentStatus;
use Src\Modules\Billing\Application\Gateway\GatewayPreapproval;
use Src\Modules\Billing\Application\Gateway\GatewayPreapprovalInput;

/**
 * Abstração do provedor de pagamentos (Mercado Pago). Permite mockar nos
 * testes e trocar de gateway sem tocar nos use cases.
 */
interface PaymentGateway
{
    public function createPixPayment(GatewayChargeInput $input): GatewayCharge;

    public function createBoletoPayment(GatewayChargeInput $input): GatewayCharge;

    public function createCardPayment(GatewayChargeInput $input): GatewayCharge;

    public function createPreapproval(GatewayPreapprovalInput $input): GatewayPreapproval;

    public function cancelPreapproval(string $preapprovalId): void;

    public function getPayment(string $mpPaymentId): GatewayPaymentStatus;

    public function cancelPayment(string $mpPaymentId): void;

    /** Public key do ambiente atual (para o Bricks/MP.js no front). */
    public function publicKey(): ?string;

    /**
     * Preapproval com cartão (assinatura automática). No sandbox do MP o
     * endpoint falha com "Card token service not found"; nesse caso usamos
     * cobrança avulsa + faturas manuais.
     */
    public function supportsAutomaticCardRecurring(): bool;
}

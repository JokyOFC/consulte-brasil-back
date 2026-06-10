<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\Gateway;

/**
 * Dados para criar uma cobrança avulsa (PIX/boleto/cartão) no gateway.
 */
final readonly class GatewayChargeInput
{
    public function __construct(
        public int $amountCents,
        public string $description,
        public string $externalReference,
        public string $payerEmail,
        public ?string $payerFirstName = null,
        public ?string $payerLastName = null,
        public ?string $payerDocument = null,
        public string $payerDocumentType = 'CPF',
        // Específicos de cartão (tokenização no front via Bricks/MP.js).
        public ?string $cardToken = null,
        public int $installments = 1,
        public ?string $paymentMethodId = null,
        public ?string $issuerId = null,
    ) {}
}

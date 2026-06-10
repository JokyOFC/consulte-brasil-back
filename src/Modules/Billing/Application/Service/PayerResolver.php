<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\Service;

use Src\Modules\Billing\Application\Gateway\GatewayChargeInput;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;

/**
 * Monta os dados do pagador (payer) a partir da conta + e-mail informado.
 * Centraliza a quebra de nome e o tipo de documento (CPF/CNPJ).
 */
final class PayerResolver
{
    /** @param array<string, mixed> $card */
    public function build(
        string $accountId,
        int $amountCents,
        string $description,
        string $externalReference,
        string $payerEmail,
        array $card = [],
    ): GatewayChargeInput {
        $account = AccountModel::query()->find($accountId);
        $name = (string) ($account->name ?? 'Cliente');
        $parts = preg_split('/\s+/', trim($name)) ?: [$name];
        $firstName = $parts[0] ?? $name;
        $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $firstName;

        $document = $account->document ?? null;
        $documentType = ($account->document_type ?? 'cnpj') === 'cpf' ? 'CPF' : 'CNPJ';

        return new GatewayChargeInput(
            amountCents: $amountCents,
            description: $description,
            externalReference: $externalReference,
            payerEmail: $payerEmail,
            payerFirstName: $firstName,
            payerLastName: $lastName,
            payerDocument: $document !== null ? preg_replace('/\D/', '', (string) $document) : null,
            payerDocumentType: $documentType,
            cardToken: $card['token'] ?? null,
            installments: (int) ($card['installments'] ?? 1),
            paymentMethodId: $card['payment_method_id'] ?? null,
            issuerId: $card['issuer_id'] ?? null,
        );
    }
}

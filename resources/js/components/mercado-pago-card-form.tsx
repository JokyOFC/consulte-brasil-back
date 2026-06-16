import { CardPayment, initMercadoPago } from '@mercadopago/sdk-react';
import { useEffect, useId, useRef } from 'react';

export interface MercadoPagoCardToken {
    card_token: string;
    installments: number;
    payment_method_id: string;
    issuer_id: string;
}

interface MercadoPagoCardFormProps {
    publicKey: string;
    /** Valor em centavos */
    amountCents: number;
    payerEmail?: string;
    submitLabel?: string;
    /** Máximo de parcelas (1 = à vista, sem seletor de parcelas). */
    maxInstallments?: number;
    onSubmit: (token: MercadoPagoCardToken) => Promise<void>;
    onError?: (message: string) => void;
}

let mercadoPagoInitialized = false;

function initOnce(publicKey: string): void {
    if (mercadoPagoInitialized) {
        return;
    }

    initMercadoPago(publicKey, { locale: 'pt-BR' });
    mercadoPagoInitialized = true;
}

export function MercadoPagoCardForm({
    publicKey,
    amountCents,
    payerEmail,
    submitLabel = 'Pagar',
    maxInstallments,
    onSubmit,
    onError,
}: MercadoPagoCardFormProps) {
    const brickId = useId().replace(/:/g, '');
    const onSubmitRef = useRef(onSubmit);

    useEffect(() => {
        onSubmitRef.current = onSubmit;
    }, [onSubmit]);

    useEffect(() => {
        if (publicKey) {
            initOnce(publicKey);
        }
    }, [publicKey]);

    if (!publicKey) {
        return (
            <p className="rounded-md bg-muted p-3 text-xs text-muted-foreground">
                Pagamento com cartão indisponível: chave pública do Mercado Pago não configurada.
            </p>
        );
    }

    const amountReais = Math.max(amountCents / 100, 0.01);

    if (amountCents <= 0) {
        return (
            <p className="rounded-md bg-muted p-3 text-xs text-muted-foreground">
                Informe um valor válido para pagar com cartão.
            </p>
        );
    }

    return (
        <div className="overflow-hidden rounded-md border border-border bg-background p-1">
            <CardPayment
                key={`${brickId}-${amountCents}-${maxInstallments ?? 'default'}`}
                id={`mp-card-${brickId}`}
                locale="pt-BR"
                initialization={{
                    amount: amountReais,
                    payer: payerEmail ? { email: payerEmail } : undefined,
                }}
                customization={{
                    ...(maxInstallments !== undefined && {
                        paymentMethods: {
                            minInstallments: 1,
                            maxInstallments,
                        },
                    }),
                    visual: {
                        texts: {
                            formSubmit: submitLabel,
                        },
                    },
                }}
                onSubmit={async (formData) => {
                    await onSubmitRef.current({
                        card_token: formData.token,
                        installments: formData.installments,
                        payment_method_id: formData.payment_method_id,
                        issuer_id: formData.issuer_id,
                    });
                }}
                onError={(error) => {
                    onError?.(error.message ?? 'Erro ao carregar formulário de cartão.');
                }}
            />
        </div>
    );
}

export function parseAmountReais(value: string): number {
    const normalized = value.trim().replace(',', '.');
    const parsed = Number.parseFloat(normalized);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
}

<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Gateway\MercadoPago;

use DateTimeImmutable;
use Illuminate\Support\Str;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\PreApproval\PreApprovalClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use Src\Modules\Billing\Application\Gateway\GatewayCharge;
use Src\Modules\Billing\Application\Gateway\GatewayChargeInput;
use Src\Modules\Billing\Application\Gateway\GatewayPaymentStatus;
use Src\Modules\Billing\Application\Gateway\GatewayPreapproval;
use Src\Modules\Billing\Application\Gateway\GatewayPreapprovalInput;
use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Domain\Exception\PaymentGatewayError;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;
use Throwable;

/**
 * Gateway de pagamentos via SDK oficial do Mercado Pago.
 *
 * O ambiente (sandbox/produção) é controlado pelo provider "mercado_pago"
 * na tela de Provedores, espelhando o padrão dos provedores de dados. O
 * token de acesso correspondente vem de config/services.php (mercado_pago).
 */
final class MercadoPagoGateway implements PaymentGateway
{
    public function __construct(
        /** @var array<string, mixed> */
        private readonly array $config,
    ) {}

    public function createPixPayment(GatewayChargeInput $input): GatewayCharge
    {
        return $this->createPayment($input, [
            'payment_method_id' => 'pix',
            'date_of_expiration' => $this->pixExpiration()->format('Y-m-d\TH:i:s.vP'),
        ]);
    }

    public function createBoletoPayment(GatewayChargeInput $input): GatewayCharge
    {
        return $this->createPayment($input, [
            'payment_method_id' => 'bolbradesco',
            'date_of_expiration' => $this->boletoExpiration()->format('Y-m-d\TH:i:s.vP'),
        ]);
    }

    public function createCardPayment(GatewayChargeInput $input): GatewayCharge
    {
        if ($input->cardToken === null || $input->paymentMethodId === null) {
            throw PaymentGatewayError::from('Cartão exige token e payment_method_id (tokenize no front).');
        }

        return $this->createPayment($input, [
            'token' => $input->cardToken,
            'installments' => $input->installments,
            'payment_method_id' => $input->paymentMethodId,
            'issuer_id' => $input->issuerId,
            'capture' => true,
        ]);
    }

    public function createPreapproval(GatewayPreapprovalInput $input): GatewayPreapproval
    {
        $this->configure();

        $request = [
            'reason' => $input->reason,
            'external_reference' => $input->externalReference,
            'payer_email' => $input->payerEmail,
            'back_url' => $input->backUrl,
            'auto_recurring' => [
                'frequency' => $input->frequency,
                'frequency_type' => $input->frequencyType,
                'transaction_amount' => $this->toReais($input->amountCents),
                'currency_id' => 'BRL',
            ],
            'status' => $input->cardTokenId !== null ? 'authorized' : 'pending',
        ];

        if ($input->cardTokenId !== null) {
            $request['card_token_id'] = $input->cardTokenId;
        }

        try {
            $result = (new PreApprovalClient)->create($request, $this->requestOptions());
        } catch (MPApiException $e) {
            throw PaymentGatewayError::from($this->describe($e));
        } catch (Throwable $e) {
            throw PaymentGatewayError::from($e->getMessage());
        }

        return new GatewayPreapproval(
            mpPreapprovalId: (string) $result->id,
            status: (string) ($result->status ?? 'pending'),
            initPoint: $result->init_point ?? null,
        );
    }

    public function cancelPreapproval(string $preapprovalId): void
    {
        $this->configure();

        try {
            (new PreApprovalClient)->update($preapprovalId, ['status' => 'cancelled'], $this->requestOptions());
        } catch (MPApiException $e) {
            throw PaymentGatewayError::from($this->describe($e));
        } catch (Throwable $e) {
            throw PaymentGatewayError::from($e->getMessage());
        }
    }

    public function getPayment(string $mpPaymentId): GatewayPaymentStatus
    {
        $this->configure();

        try {
            $payment = (new PaymentClient)->get((int) $mpPaymentId);
        } catch (MPApiException $e) {
            throw PaymentGatewayError::from($this->describe($e));
        } catch (Throwable $e) {
            throw PaymentGatewayError::from($e->getMessage());
        }

        return new GatewayPaymentStatus(
            mpPaymentId: (string) $payment->id,
            status: (string) ($payment->status ?? 'pending'),
            amountCents: (int) round(((float) ($payment->transaction_amount ?? 0)) * 100),
            externalReference: $payment->external_reference ?? null,
            paidAt: $this->parseDate($payment->date_approved ?? null),
        );
    }

    public function publicKey(): ?string
    {
        return $this->isProduction()
            ? ($this->config['public_key'] ?? null)
            : ($this->config['sandbox_public_key'] ?? $this->config['public_key'] ?? null);
    }

    public function supportsAutomaticCardRecurring(): bool
    {
        return $this->isProduction();
    }

    /** @param array<string, mixed> $extra */
    private function createPayment(GatewayChargeInput $input, array $extra): GatewayCharge
    {
        $this->configure();

        $request = array_filter(array_merge([
            'transaction_amount' => $this->toReais($input->amountCents),
            'description' => $input->description,
            'external_reference' => $input->externalReference,
            'payer' => array_filter([
                'email' => $input->payerEmail,
                'first_name' => $input->payerFirstName,
                'last_name' => $input->payerLastName,
                'identification' => $input->payerDocument !== null ? [
                    'type' => $input->payerDocumentType,
                    'number' => $input->payerDocument,
                ] : null,
            ], static fn ($v) => $v !== null),
        ], $extra), static fn ($v) => $v !== null);

        try {
            $payment = (new PaymentClient)->create($request, $this->requestOptions());
        } catch (MPApiException $e) {
            throw PaymentGatewayError::from($this->describe($e));
        } catch (Throwable $e) {
            throw PaymentGatewayError::from($e->getMessage());
        }

        $poi = $payment->point_of_interaction ?? null;
        $txData = $poi->transaction_data ?? null;
        $txDetails = $payment->transaction_details ?? null;

        return new GatewayCharge(
            mpPaymentId: (string) $payment->id,
            status: (string) ($payment->status ?? 'pending'),
            qrCode: $txData->qr_code ?? null,
            qrCodeBase64: $txData->qr_code_base64 ?? null,
            ticketUrl: $txDetails->external_resource_url ?? ($txData->ticket_url ?? null),
            barcode: $payment->barcode->content ?? null,
            expiresAt: $this->parseDate($payment->date_of_expiration ?? null),
        );
    }

    private function configure(): void
    {
        $token = $this->accessToken();

        if ($token === null || $token === '') {
            throw PaymentGatewayError::from('Token de acesso do Mercado Pago não configurado.');
        }

        MercadoPagoConfig::setAccessToken($token);
        MercadoPagoConfig::setRuntimeEnviroment(
            $this->isProduction() ? MercadoPagoConfig::SERVER : MercadoPagoConfig::LOCAL,
        );
    }

    private function requestOptions(): RequestOptions
    {
        // Idempotência por requisição (evita duplicar cobrança em retry).
        $options = new RequestOptions;
        $options->setCustomHeaders(['X-Idempotency-Key: '.Str::uuid()->toString()]);

        return $options;
    }

    private function accessToken(): ?string
    {
        return $this->isProduction()
            ? ($this->config['access_token'] ?? null)
            : ($this->config['sandbox_access_token'] ?? $this->config['access_token'] ?? null);
    }

    private function isProduction(): bool
    {
        $environment = ProviderModel::query()
            ->where('identifier', 'mercado_pago')
            ->value('environment');

        return $environment === 'production';
    }

    private function toReais(int $cents): float
    {
        return round($cents / 100, 2);
    }

    private function pixExpiration(): DateTimeImmutable
    {
        $minutes = (int) ($this->config['pix_expiration_minutes'] ?? 30);

        return new DateTimeImmutable("+{$minutes} minutes");
    }

    private function boletoExpiration(): DateTimeImmutable
    {
        $days = (int) ($this->config['boleto_expiration_days'] ?? 3);

        return new DateTimeImmutable("+{$days} days");
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function describe(MPApiException $e): string
    {
        $response = $e->getApiResponse();
        $content = $response?->getContent();

        if (is_array($content)) {
            $message = $content['message'] ?? null;
            if (is_string($message)) {
                return $message;
            }
        }

        return $e->getMessage();
    }
}

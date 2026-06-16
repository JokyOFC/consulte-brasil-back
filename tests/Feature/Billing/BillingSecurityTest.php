<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Modules\Billing\Application\DTO\PayInvoiceInput;
use Src\Modules\Billing\Application\DTO\SubscribeToPlanInput;
use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Application\UseCase\CancelSubscription;
use Src\Modules\Billing\Application\UseCase\PayInvoice;
use Src\Modules\Billing\Application\UseCase\SubscribeToPlan;
use Src\Modules\Billing\Domain\Exception\InvoiceNotFound;
use Src\Modules\Billing\Domain\Exception\SubscriptionNotFound;
use Src\Modules\Billing\Domain\Repository\InvoiceRepository;
use Src\Modules\Billing\Domain\ValueObject\PaymentMethod;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Shared\Application\Contracts\IdGenerator;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\TestCase;

final class BillingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    private function seedAccount(string $suffix = ''): string
    {
        $document = match ($suffix) {
            'b' => '39053344705',
            default => '11.222.333/0001-81',
        };

        return app(CreateAccount::class)->handle(new CreateAccountInput('ACME '.$suffix, $document))->id->value;
    }

    private function seedPlan(): string
    {
        $id = app(IdGenerator::class)->generate();
        PlanModel::query()->create([
            'id' => $id,
            'name' => 'Plano Pro',
            'slug' => 'pro-'.substr($id, 0, 8),
            'price_cents' => 9900,
            'currency' => 'BRL',
            'billing_period' => 'monthly',
            'included_credits' => 10000,
            'overage_price_cents' => null,
            'features' => [],
            'status' => 'active',
        ]);

        return $id;
    }

    public function test_cancel_subscription_rejects_other_account(): void
    {
        $victimAccountId = $this->seedAccount();
        $attackerAccountId = $this->seedAccount('b');
        $planId = $this->seedPlan();

        $result = app(SubscribeToPlan::class)->handle(new SubscribeToPlanInput(
            accountId: $victimAccountId,
            planId: $planId,
            method: PaymentMethod::CreditCard,
            payerEmail: 'victim@example.com',
            backUrl: 'https://app/billing',
            cardToken: 'tok_card',
        ));

        $this->expectException(SubscriptionNotFound::class);

        app(CancelSubscription::class)->handle($result['subscription']->id, $attackerAccountId);
    }

    public function test_pay_invoice_rejects_other_account(): void
    {
        $victimAccountId = $this->seedAccount();
        $attackerAccountId = $this->seedAccount('b');
        $planId = $this->seedPlan();

        $result = app(SubscribeToPlan::class)->handle(new SubscribeToPlanInput(
            accountId: $victimAccountId,
            planId: $planId,
            method: PaymentMethod::Pix,
            payerEmail: 'victim@example.com',
            backUrl: 'https://app/billing',
        ));

        $invoice = app(InvoiceRepository::class)->findById($result['invoice']->id);
        $this->assertNotNull($invoice);

        $attacker = User::factory()->create(['account_id' => $attackerAccountId, 'role' => 'client']);

        $this->actingAs($attacker)
            ->post(route('client.billing.invoices.pay'), [
                'invoice_id' => $invoice->id,
                'method' => 'pix',
                'payer_email' => 'attacker@example.com',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->expectException(InvoiceNotFound::class);

        app(PayInvoice::class)->handle(new PayInvoiceInput(
            accountId: $attackerAccountId,
            invoiceId: $invoice->id,
            method: PaymentMethod::Pix,
            payerEmail: 'attacker@example.com',
        ));
    }

    public function test_webhook_rejects_missing_secret_in_production(): void
    {
        config(['services.mercado_pago.webhook_secret' => '']);
        $this->app->detectEnvironment(fn () => 'production');

        $this->postJson('/api/v1/webhooks/mercadopago', [
            'type' => 'payment',
            'data' => ['id' => '12345'],
        ])->assertUnauthorized();
    }

    public function test_webhook_rejects_stale_signature_timestamp(): void
    {
        $secret = 'test-webhook-secret';
        config(['services.mercado_pago.webhook_secret' => $secret]);

        $dataId = '12345';
        $staleTs = time() - 600;
        $headers = $this->mercadoPagoSignatureHeaders($dataId, $secret, $staleTs);

        $this->postJson('/api/v1/webhooks/mercadopago', [
            'type' => 'payment',
            'data' => ['id' => $dataId],
        ], $headers)->assertUnauthorized();
    }

    public function test_webhook_accepts_valid_signature(): void
    {
        $secret = 'test-webhook-secret';
        config(['services.mercado_pago.webhook_secret' => $secret]);

        $dataId = '99999';
        $headers = $this->mercadoPagoSignatureHeaders($dataId, $secret);

        $this->postJson('/api/v1/webhooks/mercadopago', [
            'type' => 'payment',
            'data' => ['id' => $dataId],
        ], $headers)->assertOk()->assertJson(['received' => true]);
    }

    /** @return array<string, string> */
    private function mercadoPagoSignatureHeaders(string $dataId, string $secret, ?int $ts = null): array
    {
        $ts ??= time();
        $requestId = 'req-test-1';
        $manifest = sprintf('id:%s;request-id:%s;ts:%s;', strtolower($dataId), $requestId, $ts);
        $v1 = hash_hmac('sha256', $manifest, $secret);

        return [
            'x-signature' => "ts={$ts},v1={$v1}",
            'x-request-id' => $requestId,
        ];
    }
}

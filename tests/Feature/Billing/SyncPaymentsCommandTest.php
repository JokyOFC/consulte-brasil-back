<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Modules\Billing\Application\DTO\CreateWalletTopupInput;
use Src\Modules\Billing\Application\Gateway\GatewayPaymentStatus;
use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Application\UseCase\CreateWalletTopup;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Billing\Domain\ValueObject\PaymentMethod;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\TestCase;

final class SyncPaymentsCommandTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    public function test_command_syncs_pending_payments_from_mercado_pago(): void
    {
        $accountId = app(CreateAccount::class)->handle(new CreateAccountInput('ACME', '11.222.333/0001-81'))->id->value;

        $payment = app(CreateWalletTopup::class)->handle(new CreateWalletTopupInput(
            accountId: $accountId,
            amountCents: 5000,
            method: PaymentMethod::Pix,
            payerEmail: 'a@b.com',
        ));

        $mpId = $payment->mpPaymentId;
        $this->assertNotNull($mpId);

        $this->gateway->remoteStatuses[$mpId] = new GatewayPaymentStatus(
            mpPaymentId: $mpId,
            status: 'approved',
            amountCents: 5000,
            externalReference: "payment:{$payment->id}",
        );

        $this->artisan('billing:sync-payments')
            ->assertSuccessful()
            ->expectsOutputToContain('liquidado');

        $this->assertSame(5000, app(WalletRepository::class)->findByAccountId($accountId)?->balance()->value ?? 0);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\PaymentConfirmedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Src\Modules\Billing\Application\DTO\CreateWalletTopupInput;
use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Application\UseCase\CreateWalletTopup;
use Src\Modules\Billing\Application\UseCase\HandleMercadoPagoWebhook;
use Src\Modules\Billing\Domain\ValueObject\PaymentMethod;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\TestCase;

final class PaymentConfirmedEmailTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    private function seedUserForAccount(string $accountId): User
    {
        return User::factory()->create([
            'account_id' => $accountId,
            'email' => 'billing@acme.test',
        ]);
    }

    public function test_approved_topup_sends_payment_confirmed_email(): void
    {
        Mail::fake();

        $accountId = app(CreateAccount::class)->handle(new CreateAccountInput('ACME', '11.222.333/0001-81'))->id->value;
        $this->seedUserForAccount($accountId);

        app(CreateWalletTopup::class)->handle(new CreateWalletTopupInput(
            accountId: $accountId,
            amountCents: 5000,
            method: PaymentMethod::Pix,
            payerEmail: 'billing@acme.test',
        ));

        $mpId = $this->gateway->approveCharge(0);
        app(HandleMercadoPagoWebhook::class)->handle('payment', $mpId);

        Mail::assertQueued(PaymentConfirmedMail::class, function (PaymentConfirmedMail $mail): bool {
            return $mail->hasTo('billing@acme.test')
                && $mail->amountCents === 5000
                && $mail->creditsGranted === 5000;
        });
    }

    public function test_duplicate_webhook_does_not_send_second_payment_email(): void
    {
        Mail::fake();

        $accountId = app(CreateAccount::class)->handle(new CreateAccountInput('ACME', '11.222.333/0001-81'))->id->value;
        $this->seedUserForAccount($accountId);

        app(CreateWalletTopup::class)->handle(new CreateWalletTopupInput(
            accountId: $accountId,
            amountCents: 5000,
            method: PaymentMethod::Pix,
            payerEmail: 'billing@acme.test',
        ));

        $mpId = $this->gateway->approveCharge(0);
        $webhook = app(HandleMercadoPagoWebhook::class);
        $webhook->handle('payment', $mpId);
        $webhook->handle('payment.updated', $mpId);

        Mail::assertQueued(PaymentConfirmedMail::class, 1);
    }
}

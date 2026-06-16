<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Modules\Billing\Application\DTO\CreateWalletTopupInput;
use Src\Modules\Billing\Application\DTO\SubscribeToPlanInput;
use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Application\UseCase\CancelSubscription;
use Src\Modules\Billing\Application\UseCase\CreateWalletTopup;
use Src\Modules\Billing\Application\UseCase\HandleMercadoPagoWebhook;
use Src\Modules\Billing\Application\UseCase\RunRecurringBilling;
use Src\Modules\Billing\Application\UseCase\SubscribeToPlan;
use Src\Modules\Billing\Domain\Entity\Invoice;
use Src\Modules\Billing\Domain\Entity\InvoiceItem;
use Src\Modules\Billing\Domain\Entity\Subscription;
use Src\Modules\Billing\Domain\Repository\InvoiceRepository;
use Src\Modules\Billing\Domain\Repository\SubscriptionRepository;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Billing\Domain\ValueObject\InvoiceStatus;
use Src\Modules\Billing\Domain\ValueObject\PaymentMethod;
use Src\Modules\Billing\Domain\ValueObject\PaymentStatus;
use Src\Modules\Billing\Domain\ValueObject\SubscriptionStatus;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Shared\Application\Contracts\IdGenerator;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\TestCase;

final class MercadoPagoBillingTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    private function seedAccount(): string
    {
        return app(CreateAccount::class)->handle(new CreateAccountInput('ACME', '11.222.333/0001-81'))->id->value;
    }

    private function seedPlan(int $priceCents = 9900, int $includedCredits = 10000): string
    {
        $id = app(IdGenerator::class)->generate();
        PlanModel::query()->create([
            'id' => $id,
            'name' => 'Plano Pro',
            'slug' => 'pro-'.substr($id, 0, 8),
            'price_cents' => $priceCents,
            'currency' => 'BRL',
            'billing_period' => 'monthly',
            'included_credits' => $includedCredits,
            'overage_price_cents' => null,
            'features' => [],
            'status' => 'active',
        ]);

        return $id;
    }

    private function balance(string $accountId): int
    {
        return app(WalletRepository::class)->findByAccountId($accountId)?->balance()->value ?? 0;
    }

    public function test_pix_topup_only_credits_wallet_after_approved_webhook_and_is_idempotent(): void
    {
        $accountId = $this->seedAccount();

        $payment = app(CreateWalletTopup::class)->handle(new CreateWalletTopupInput(
            accountId: $accountId,
            amountCents: 5000,
            method: PaymentMethod::Pix,
            payerEmail: 'a@b.com',
        ));

        $this->assertNotSame(PaymentStatus::Approved, $payment->status);
        $this->assertSame(0, $this->balance($accountId));

        $mpId = $this->gateway->approveCharge(0);
        app(HandleMercadoPagoWebhook::class)->handle('payment', $mpId);
        $this->assertSame(5000, $this->balance($accountId));

        // Webhook duplicado não credita novamente.
        app(HandleMercadoPagoWebhook::class)->handle('payment.updated', $mpId);
        $this->assertSame(5000, $this->balance($accountId));
    }

    public function test_card_topup_credits_wallet_immediately(): void
    {
        $accountId = $this->seedAccount();

        $payment = app(CreateWalletTopup::class)->handle(new CreateWalletTopupInput(
            accountId: $accountId,
            amountCents: 7500,
            method: PaymentMethod::CreditCard,
            payerEmail: 'a@b.com',
            cardToken: 'tok_123',
        ));

        $this->assertSame(PaymentStatus::Approved, $payment->status);
        $this->assertSame(7500, $this->balance($accountId));
    }

    public function test_subscribe_manual_creates_subscription_invoice_and_recharges_on_payment(): void
    {
        $accountId = $this->seedAccount();
        $planId = $this->seedPlan(priceCents: 9900, includedCredits: 10000);

        $result = app(SubscribeToPlan::class)->handle(new SubscribeToPlanInput(
            accountId: $accountId,
            planId: $planId,
            method: PaymentMethod::Pix,
            payerEmail: 'a@b.com',
            backUrl: 'https://app/billing',
        ));

        $this->assertArrayHasKey('payment', $result);
        $this->assertDatabaseHas('subscriptions', [
            'account_id' => $accountId,
            'plan_id' => $planId,
            'payment_method' => 'manual',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('invoices', ['account_id' => $accountId, 'status' => 'open']);

        $mpId = $this->gateway->approveCharge(0);
        app(HandleMercadoPagoWebhook::class)->handle('payment', $mpId);

        // Recarga = included_credits do plano.
        $this->assertSame(10000, $this->balance($accountId));
        $this->assertDatabaseHas('invoices', ['account_id' => $accountId, 'status' => 'paid']);
    }

    public function test_subscribe_with_card_creates_preapproval(): void
    {
        $accountId = $this->seedAccount();
        $planId = $this->seedPlan();

        $result = app(SubscribeToPlan::class)->handle(new SubscribeToPlanInput(
            accountId: $accountId,
            planId: $planId,
            method: PaymentMethod::CreditCard,
            payerEmail: 'a@b.com',
            backUrl: 'https://app/billing',
            cardToken: 'tok_card',
        ));

        $this->assertArrayNotHasKey('payment', $result);
        $sub = DB::table('subscriptions')->where('account_id', $accountId)->first();
        $this->assertNotNull($sub->mp_preapproval_id);
        $this->assertSame('credit_card', $sub->payment_method);
    }

    public function test_subscribe_with_card_in_sandbox_uses_manual_invoice_payment(): void
    {
        $this->gateway->automaticCardRecurringEnabled = false;
        $this->gateway->nextChargeStatus = 'approved';

        $accountId = $this->seedAccount();
        $planId = $this->seedPlan(priceCents: 9900, includedCredits: 10000);

        $result = app(SubscribeToPlan::class)->handle(new SubscribeToPlanInput(
            accountId: $accountId,
            planId: $planId,
            method: PaymentMethod::CreditCard,
            payerEmail: 'a@b.com',
            backUrl: 'https://app/billing',
            cardToken: 'tok_card',
            paymentMethodId: 'visa',
        ));

        $this->assertArrayHasKey('payment', $result);
        $this->assertSame(PaymentStatus::Approved, $result['payment']->status);
        $this->assertDatabaseHas('subscriptions', [
            'account_id' => $accountId,
            'payment_method' => 'manual',
            'status' => 'active',
        ]);
        $this->assertSame(10000, $this->balance($accountId));
        $this->assertSame('credit_card', $this->gateway->charges[0]['method']);
    }

    public function test_cancel_subscription_cancels_preapproval_remotely(): void
    {
        $accountId = $this->seedAccount();
        $planId = $this->seedPlan();

        $result = app(SubscribeToPlan::class)->handle(new SubscribeToPlanInput(
            accountId: $accountId,
            planId: $planId,
            method: PaymentMethod::CreditCard,
            payerEmail: 'a@b.com',
            backUrl: 'https://app/billing',
            cardToken: 'tok_card',
        ));

        app(CancelSubscription::class)->handle($result['subscription']->id, $accountId);

        $this->assertSame(1, $this->gateway->cancelledPreapprovals);
        $this->assertDatabaseHas('subscriptions', ['id' => $result['subscription']->id, 'status' => 'cancelled']);
    }

    public function test_run_recurring_marks_overdue_and_generates_next_invoice(): void
    {
        $accountId = $this->seedAccount();
        $planId = $this->seedPlan(priceCents: 9900);
        $past = new DateTimeImmutable('-2 days');

        $subscriptionId = app(IdGenerator::class)->generate();
        app(SubscriptionRepository::class)->save(new Subscription(
            id: $subscriptionId,
            accountId: $accountId,
            planId: $planId,
            status: SubscriptionStatus::Active,
            paymentMethod: 'manual',
            currentPeriodStart: new DateTimeImmutable('-32 days'),
            currentPeriodEnd: $past,
            renewsAt: $past,
            nextBillingAt: $past,
            createdAt: new DateTimeImmutable('-32 days'),
        ));

        app(InvoiceRepository::class)->save(new Invoice(
            id: app(IdGenerator::class)->generate(),
            accountId: $accountId,
            subscriptionId: $subscriptionId,
            status: InvoiceStatus::Open,
            amountCents: 9900,
            description: 'Plano Pro',
            dueDate: $past,
            periodStart: new DateTimeImmutable('-32 days'),
            periodEnd: $past,
            items: [new InvoiceItem(app(IdGenerator::class)->generate(), 'Plano Pro', 9900)],
            createdAt: new DateTimeImmutable('-32 days'),
        ));

        $result = app(RunRecurringBilling::class)->handle();

        $this->assertSame(1, $result['overdue']);
        $this->assertSame(1, $result['invoices_generated']);
        $this->assertSame(1, DB::table('invoices')->where('status', 'overdue')->count());
        $this->assertSame(2, DB::table('invoices')->where('subscription_id', $subscriptionId)->count());

        $sub = DB::table('subscriptions')->where('id', $subscriptionId)->first();
        $this->assertTrue(new DateTimeImmutable((string) $sub->next_billing_at) > new DateTimeImmutable);
    }

    public function test_webhook_ignores_non_payment_notifications(): void
    {
        $accountId = $this->seedAccount();
        app(HandleMercadoPagoWebhook::class)->handle('preapproval', 'pre_1');
        $this->assertSame(0, $this->balance($accountId));
    }
}

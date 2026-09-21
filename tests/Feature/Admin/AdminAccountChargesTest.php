<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Src\Modules\Billing\Application\DTO\CreatePlanInput;
use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Application\UseCase\CreatePlan;
use Src\Modules\Billing\Application\UseCase\HandleMercadoPagoWebhook;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\InvoiceModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PaymentModel;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\TestCase;

/**
 * Admin gera cobranças PIX em nome do cliente: plano (assinatura + 1ª fatura),
 * recarga avulsa e fatura em aberto. O saldo só entra após o pagamento.
 */
final class AdminAccountChargesTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function seedAccount(string $document = '11.222.333/0001-81'): string
    {
        return app(CreateAccount::class)->handle(new CreateAccountInput('ACME', $document))->id->value;
    }

    private function seedClient(string $accountId, string $email = 'financeiro@acme.test'): User
    {
        return User::factory()->create(['role' => 'client', 'account_id' => $accountId, 'email' => $email]);
    }

    private function seedPlan(int $priceCents = 9900, int $includedCredits = 12000, string $slug = 'pro'): string
    {
        return app(CreatePlan::class)->handle(new CreatePlanInput(
            name: 'Pro',
            slug: $slug,
            priceCents: $priceCents,
            includedCredits: $includedCredits,
        ))->id;
    }

    private function balance(string $accountId): int
    {
        return app(WalletRepository::class)->findByAccountId($accountId)?->balance()->value ?? 0;
    }

    public function test_admin_assigns_plan_with_pix_and_balance_only_enters_after_payment(): void
    {
        $accountId = $this->seedAccount();
        $this->seedClient($accountId);
        $planId = $this->seedPlan();

        $response = $this->actingAs($this->admin)
            ->withConfirmedPassword()
            ->post("/admin/accounts/{$accountId}/charges/plan", ['plan_id' => $planId])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $payment = $response->getSession()->get('payment');
        $this->assertSame('pix', $payment['method']);
        $this->assertSame('invoice', $payment['type']);
        $this->assertSame(9900, $payment['amount_cents']);
        $this->assertSame('pix-copia-e-cola', $payment['qr_code']);

        // O pagador é o usuário (cliente) da conta — não o admin logado.
        $this->assertSame('pix', $this->gateway->charges[0]['method']);
        $this->assertSame('financeiro@acme.test', $this->gateway->charges[0]['input']->payerEmail);

        $this->assertDatabaseHas('subscriptions', [
            'account_id' => $accountId,
            'plan_id' => $planId,
            'status' => 'active',
            'payment_method' => 'manual',
            'price_cents' => 9900,
        ]);
        $this->assertDatabaseHas('invoices', ['account_id' => $accountId, 'status' => 'open', 'amount_cents' => 9900]);
        $this->assertSame(0, $this->balance($accountId), 'Sem pagamento, sem saldo.');

        $mpId = $this->gateway->approveCharge(0);
        app(HandleMercadoPagoWebhook::class)->handle('payment', $mpId);

        $this->assertSame(12000, $this->balance($accountId));
        $this->assertDatabaseHas('invoices', ['account_id' => $accountId, 'status' => 'paid']);
    }

    public function test_plan_charge_is_blocked_while_account_has_active_subscription(): void
    {
        $accountId = $this->seedAccount();
        $this->seedClient($accountId);
        $planId = $this->seedPlan();

        $this->actingAs($this->admin)
            ->withConfirmedPassword()
            ->post("/admin/accounts/{$accountId}/charges/plan", ['plan_id' => $planId])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->withConfirmedPassword()
            ->from("/admin/accounts/{$accountId}")
            ->post("/admin/accounts/{$accountId}/charges/plan", ['plan_id' => $planId])
            ->assertRedirect("/admin/accounts/{$accountId}")
            ->assertSessionHasErrors('plan_id');

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertCount(1, $this->gateway->charges);
    }

    public function test_plan_charge_leaves_nothing_behind_when_gateway_fails(): void
    {
        $accountId = $this->seedAccount();
        $this->seedClient($accountId);
        $planId = $this->seedPlan();
        $this->gateway->failCharges = true;

        $this->actingAs($this->admin)
            ->withConfirmedPassword()
            ->post("/admin/accounts/{$accountId}/charges/plan", ['plan_id' => $planId])
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionMissing('payment');

        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_plan_charge_rejects_archived_or_free_plans(): void
    {
        $accountId = $this->seedAccount();
        $this->seedClient($accountId);
        $freePlanId = $this->seedPlan(priceCents: 0, slug: 'free');
        $archivedPlanId = $this->seedPlan(slug: 'old');
        DB::table('plans')->where('id', $archivedPlanId)->update(['status' => 'archived']);

        foreach ([$freePlanId, $archivedPlanId] as $planId) {
            $this->actingAs($this->admin)
                ->withConfirmedPassword()
                ->post("/admin/accounts/{$accountId}/charges/plan", ['plan_id' => $planId])
                ->assertSessionHasErrors('plan_id');
        }

        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertCount(0, $this->gateway->charges);
    }

    public function test_plan_charge_requires_password_confirmation(): void
    {
        $accountId = $this->seedAccount();
        $this->seedClient($accountId);
        $planId = $this->seedPlan();

        $this->actingAs($this->admin)
            ->post("/admin/accounts/{$accountId}/charges/plan", ['plan_id' => $planId])
            ->assertRedirect(route('password.confirm'));

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_admin_generates_pix_topup(): void
    {
        $accountId = $this->seedAccount();
        $this->seedClient($accountId);

        $response = $this->actingAs($this->admin)
            ->post("/admin/accounts/{$accountId}/charges/topup", ['amount' => '150.50'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $payment = $response->getSession()->get('payment');
        $this->assertSame('topup', $payment['type']);
        $this->assertSame('pix', $payment['method']);
        $this->assertSame(15050, $payment['amount_cents']);

        $this->assertDatabaseHas('payments', [
            'account_id' => $accountId,
            'type' => 'topup',
            'method' => 'pix',
            'amount_cents' => 15050,
        ]);
        $this->assertSame(0, $this->balance($accountId));

        $mpId = $this->gateway->approveCharge(0);
        app(HandleMercadoPagoWebhook::class)->handle('payment', $mpId);

        $this->assertSame(15050, $this->balance($accountId));
    }

    public function test_payer_email_must_be_informed_when_account_has_no_user(): void
    {
        $accountId = $this->seedAccount();

        $this->actingAs($this->admin)
            ->post("/admin/accounts/{$accountId}/charges/topup", ['amount' => 50])
            ->assertSessionHasErrors('payer_email');

        $this->assertCount(0, $this->gateway->charges);

        $this->actingAs($this->admin)
            ->post("/admin/accounts/{$accountId}/charges/topup", [
                'amount' => 50,
                'payer_email' => 'pagador@acme.test',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('pagador@acme.test', $this->gateway->charges[0]['input']->payerEmail);
    }

    public function test_admin_generates_pix_for_open_invoice_of_the_account_only(): void
    {
        $accountId = $this->seedAccount();
        $this->seedClient($accountId);
        $planId = $this->seedPlan();

        $this->actingAs($this->admin)
            ->withConfirmedPassword()
            ->post("/admin/accounts/{$accountId}/charges/plan", ['plan_id' => $planId]);

        $invoiceId = (string) InvoiceModel::query()->where('account_id', $accountId)->value('id');

        // Novo PIX para a mesma fatura (ex.: o anterior expirou).
        $response = $this->actingAs($this->admin)
            ->post("/admin/accounts/{$accountId}/charges/invoices/{$invoiceId}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('invoice', $response->getSession()->get('payment')['type']);
        $this->assertSame(2, PaymentModel::query()->where('invoice_id', $invoiceId)->count());

        // Fatura de outra conta não pode ser cobrada por aqui.
        $otherAccountId = $this->seedAccount('04.252.011/0001-10');
        $this->seedClient($otherAccountId, 'outra@conta.test');

        $this->actingAs($this->admin)
            ->post("/admin/accounts/{$otherAccountId}/charges/invoices/{$invoiceId}")
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionMissing('payment');

        $this->assertCount(2, $this->gateway->charges);
    }

    public function test_admin_polls_payment_status_and_settles_when_approved(): void
    {
        $accountId = $this->seedAccount();
        $this->seedClient($accountId);

        $response = $this->actingAs($this->admin)
            ->post("/admin/accounts/{$accountId}/charges/topup", ['amount' => 80]);
        $paymentId = $response->getSession()->get('payment')['id'];

        // "pending" do Mercado Pago é mapeado para in_process (aguardando pagamento).
        $this->actingAs($this->admin)
            ->getJson("/admin/accounts/{$accountId}/charges/{$paymentId}/status")
            ->assertOk()
            ->assertJson(['id' => $paymentId, 'status' => 'in_process']);

        $this->gateway->approveCharge(0);

        $this->actingAs($this->admin)
            ->getJson("/admin/accounts/{$accountId}/charges/{$paymentId}/status")
            ->assertOk()
            ->assertJson(['status' => 'approved']);

        $this->assertSame(8000, $this->balance($accountId));

        // Pagamento de outra conta → 404.
        $otherAccountId = $this->seedAccount('04.252.011/0001-10');
        $this->actingAs($this->admin)
            ->getJson("/admin/accounts/{$otherAccountId}/charges/{$paymentId}/status")
            ->assertNotFound();
    }

    public function test_admin_cancels_subscription_and_can_assign_another_plan(): void
    {
        $accountId = $this->seedAccount();
        $this->seedClient($accountId);
        $planId = $this->seedPlan();

        $this->actingAs($this->admin)
            ->withConfirmedPassword()
            ->post("/admin/accounts/{$accountId}/charges/plan", ['plan_id' => $planId]);

        $subscriptionId = (string) DB::table('subscriptions')->where('account_id', $accountId)->value('id');

        // Escopo por conta: não cancela assinatura alheia trocando o id na URL.
        $otherAccountId = $this->seedAccount('04.252.011/0001-10');
        $this->actingAs($this->admin)
            ->withConfirmedPassword()
            ->post("/admin/accounts/{$otherAccountId}/subscriptions/{$subscriptionId}/cancel")
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->withConfirmedPassword()
            ->post("/admin/accounts/{$accountId}/subscriptions/{$subscriptionId}/cancel")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('subscriptions', ['id' => $subscriptionId, 'status' => 'cancelled']);

        $this->actingAs($this->admin)
            ->withConfirmedPassword()
            ->post("/admin/accounts/{$accountId}/charges/plan", ['plan_id' => $planId])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('subscriptions', 2);
    }

    public function test_clients_cannot_generate_charges_for_accounts(): void
    {
        $accountId = $this->seedAccount();
        $client = $this->seedClient($accountId);

        $this->actingAs($client)
            ->post("/admin/accounts/{$accountId}/charges/topup", ['amount' => 50])
            ->assertForbidden();

        $this->assertCount(0, $this->gateway->charges);
    }

    public function test_account_page_exposes_open_invoices_default_payer_and_reopenable_pix(): void
    {
        $accountId = $this->seedAccount();
        $this->seedClient($accountId);
        $planId = $this->seedPlan();

        $this->actingAs($this->admin)
            ->withConfirmedPassword()
            ->post("/admin/accounts/{$accountId}/charges/plan", ['plan_id' => $planId]);

        $this->actingAs($this->admin)
            ->get("/admin/accounts/{$accountId}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/accounts/show')
                ->where('payer_email', 'financeiro@acme.test')
                ->has('open_invoices', 1, fn (Assert $invoice) => $invoice
                    ->where('status', 'open')
                    ->where('amount_cents', 9900)
                    ->etc(),
                )
                ->where('subscription.status', 'active')
                ->where('recent_payments.0.pix.qr_code', 'pix-copia-e-cola'),
            );

        // PIX vencido/cancelado no gateway (status final) deixa de ser reabrível.
        PaymentModel::query()->where('account_id', $accountId)->update(['status' => 'cancelled']);

        $this->actingAs($this->admin)
            ->get("/admin/accounts/{$accountId}")
            ->assertInertia(fn (Assert $page) => $page->where('recent_payments.0.pix', null));
    }
}

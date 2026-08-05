<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Modules\Billing\Application\Service\InvoiceNumberGenerator;
use Src\Modules\Billing\Application\UseCase\EnsureRenewalInvoices;
use Src\Modules\Billing\Domain\Entity\Subscription;
use Src\Modules\Billing\Domain\Repository\SubscriptionRepository;
use Src\Modules\Billing\Domain\ValueObject\SubscriptionStatus;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\InvoiceModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Shared\Application\Contracts\IdGenerator;
use Tests\TestCase;

final class InvoiceNumberAndRenewalTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_number_generator_is_sequential_per_year(): void
    {
        $generator = app(InvoiceNumberGenerator::class);
        $year = (int) date('Y');

        $this->assertSame(sprintf('FAT-%d-%06d', $year, 1), $generator->next($year));
        $this->assertSame(sprintf('FAT-%d-%06d', $year, 2), $generator->next($year));
    }

    public function test_ensure_renewal_creates_one_open_invoice_per_manual_subscription(): void
    {
        $accountId = app(CreateAccount::class)->handle(new CreateAccountInput('ACME', '11.222.333/0001-81'))->id->value;
        $planId = app(IdGenerator::class)->generate();
        PlanModel::query()->create([
            'id' => $planId,
            'name' => 'Pro',
            'slug' => 'pro-renewal',
            'price_cents' => 5000,
            'currency' => 'BRL',
            'billing_period' => 'monthly',
            'included_credits' => 5000,
            'status' => 'active',
        ]);

        $now = new DateTimeImmutable;
        $subscriptionId = app(IdGenerator::class)->generate();
        app(SubscriptionRepository::class)->save(new Subscription(
            id: $subscriptionId,
            accountId: $accountId,
            planId: $planId,
            status: SubscriptionStatus::Active,
            paymentMethod: 'manual',
            currentPeriodStart: $now,
            currentPeriodEnd: $now->modify('+20 days'),
            renewsAt: $now->modify('+20 days'),
            nextBillingAt: $now->modify('+20 days'),
            createdAt: $now,
        ));

        $created = app(EnsureRenewalInvoices::class)->handle($accountId);
        $this->assertSame(1, $created);

        $again = app(EnsureRenewalInvoices::class)->handle($accountId);
        $this->assertSame(0, $again);

        $invoice = InvoiceModel::query()->where('subscription_id', $subscriptionId)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('open', $invoice->status);
        $this->assertNotNull($invoice->number);
        $this->assertSame('renewal', $invoice->metadata['origin'] ?? null);
    }

    public function test_client_cannot_access_another_accounts_invoice(): void
    {
        $accountA = app(CreateAccount::class)->handle(new CreateAccountInput('A', '11.222.333/0001-81'))->id->value;
        $accountB = app(CreateAccount::class)->handle(new CreateAccountInput('B', '11.444.555/0001-99'))->id->value;

        $invoiceId = app(IdGenerator::class)->generate();
        InvoiceModel::query()->create([
            'id' => $invoiceId,
            'number' => 'FAT-2026-000001',
            'account_id' => $accountA,
            'status' => 'open',
            'amount_cents' => 1000,
            'currency' => 'BRL',
            'description' => 'Teste',
            'due_date' => now()->addDays(3),
        ]);

        $userB = User::factory()->create(['role' => 'client', 'account_id' => $accountB]);

        $this->actingAs($userB)
            ->get("/client/invoices/{$invoiceId}")
            ->assertForbidden();
    }
}

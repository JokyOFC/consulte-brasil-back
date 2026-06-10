<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Modules\Billing\Application\DTO\GrantCreditsInput;
use Src\Modules\Billing\Application\DTO\ReserveCreditsInput;
use Src\Modules\Billing\Application\UseCase\CommitCredits;
use Src\Modules\Billing\Application\UseCase\GrantCredits;
use Src\Modules\Billing\Application\UseCase\RefundCredits;
use Src\Modules\Billing\Application\UseCase\ReserveCredits;
use Src\Modules\Billing\Domain\Exception\InsufficientCredits;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Tests\TestCase;

final class CreditLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function newAccountId(): string
    {
        return app(CreateAccount::class)
            ->handle(new CreateAccountInput('ACME Ltda', '11.222.333/0001-81'))
            ->id->value;
    }

    private function wallet(string $accountId)
    {
        return app(WalletRepository::class)->findByAccountId($accountId);
    }

    public function test_wallet_is_provisioned_when_account_is_created(): void
    {
        $accountId = $this->newAccountId();

        $wallet = $this->wallet($accountId);

        $this->assertNotNull($wallet);
        $this->assertSame(0, $wallet->balance()->value);
        $this->assertSame(0, $wallet->available()->value);
    }

    public function test_grant_reserve_commit_flow(): void
    {
        $accountId = $this->newAccountId();

        app(GrantCredits::class)->handle(new GrantCreditsInput($accountId, 100));

        $reservation = app(ReserveCredits::class)->handle(new ReserveCreditsInput($accountId, 30));
        $this->assertSame(70, $reservation->availableAfter);

        $wallet = $this->wallet($accountId);
        $this->assertSame(100, $wallet->balance()->value);
        $this->assertSame(30, $wallet->reserved()->value);

        app(CommitCredits::class)->handle($reservation->reservationId);

        $wallet = $this->wallet($accountId);
        $this->assertSame(70, $wallet->balance()->value);
        $this->assertSame(0, $wallet->reserved()->value);
        $this->assertSame(70, $wallet->available()->value);
    }

    public function test_refund_restores_available_without_spending(): void
    {
        $accountId = $this->newAccountId();
        app(GrantCredits::class)->handle(new GrantCreditsInput($accountId, 50));

        $reservation = app(ReserveCredits::class)->handle(new ReserveCreditsInput($accountId, 20));
        app(RefundCredits::class)->handle($reservation->reservationId);

        $wallet = $this->wallet($accountId);
        $this->assertSame(50, $wallet->balance()->value);
        $this->assertSame(0, $wallet->reserved()->value);
        $this->assertSame(50, $wallet->available()->value);
    }

    public function test_reserve_beyond_available_is_rejected(): void
    {
        $accountId = $this->newAccountId();
        app(GrantCredits::class)->handle(new GrantCreditsInput($accountId, 10));

        $this->expectException(InsufficientCredits::class);

        app(ReserveCredits::class)->handle(new ReserveCreditsInput($accountId, 20));
    }

    public function test_grant_is_idempotent(): void
    {
        $accountId = $this->newAccountId();

        $input = new GrantCreditsInput($accountId, 100, idempotencyKey: 'order-123');
        app(GrantCredits::class)->handle($input);
        app(GrantCredits::class)->handle($input);

        $this->assertSame(100, $this->wallet($accountId)->balance()->value);
    }

    public function test_commit_is_idempotent(): void
    {
        $accountId = $this->newAccountId();
        app(GrantCredits::class)->handle(new GrantCreditsInput($accountId, 100));
        $reservation = app(ReserveCredits::class)->handle(new ReserveCreditsInput($accountId, 30));

        app(CommitCredits::class)->handle($reservation->reservationId);
        app(CommitCredits::class)->handle($reservation->reservationId);

        $wallet = $this->wallet($accountId);
        $this->assertSame(70, $wallet->balance()->value);
        $this->assertSame(0, $wallet->reserved()->value);
    }

    public function test_refund_after_commit_is_a_noop(): void
    {
        $accountId = $this->newAccountId();
        app(GrantCredits::class)->handle(new GrantCreditsInput($accountId, 100));
        $reservation = app(ReserveCredits::class)->handle(new ReserveCreditsInput($accountId, 30));

        app(CommitCredits::class)->handle($reservation->reservationId);
        app(RefundCredits::class)->handle($reservation->reservationId);

        // O refund não desfaz um commit já liquidado: saldo permanece 70.
        $this->assertSame(70, $this->wallet($accountId)->balance()->value);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;
use Src\Modules\Billing\Domain\Entity\Wallet;
use Src\Modules\Billing\Domain\Exception\InsufficientCredits;
use Src\Modules\Billing\Domain\ValueObject\WalletId;
use Src\Shared\Domain\ValueObject\CreditAmount;

final class WalletTest extends TestCase
{
    private function wallet(int $balance = 0, int $reserved = 0): Wallet
    {
        return new Wallet(
            new WalletId('wallet-1'),
            'account-1',
            CreditAmount::of($balance),
            CreditAmount::of($reserved),
        );
    }

    public function test_available_is_balance_minus_reserved(): void
    {
        $wallet = $this->wallet(balance: 100, reserved: 30);

        $this->assertSame(70, $wallet->available()->value);
    }

    public function test_grant_increases_balance(): void
    {
        $wallet = $this->wallet(balance: 100);
        $wallet->grant(CreditAmount::of(50));

        $this->assertSame(150, $wallet->balance()->value);
        $this->assertSame(150, $wallet->available()->value);
    }

    public function test_reserve_moves_available_into_reserved(): void
    {
        $wallet = $this->wallet(balance: 100);
        $wallet->reserve(CreditAmount::of(30));

        $this->assertSame(100, $wallet->balance()->value);
        $this->assertSame(30, $wallet->reserved()->value);
        $this->assertSame(70, $wallet->available()->value);
    }

    public function test_reserve_beyond_available_throws(): void
    {
        $this->expectException(InsufficientCredits::class);

        $this->wallet(balance: 10)->reserve(CreditAmount::of(20));
    }

    public function test_commit_consumes_balance_and_reserved(): void
    {
        $wallet = $this->wallet(balance: 100, reserved: 30);
        $wallet->commit(CreditAmount::of(30));

        $this->assertSame(70, $wallet->balance()->value);
        $this->assertSame(0, $wallet->reserved()->value);
        $this->assertSame(70, $wallet->available()->value);
    }

    public function test_refund_releases_reserved_without_spending(): void
    {
        $wallet = $this->wallet(balance: 100, reserved: 30);
        $wallet->refund(CreditAmount::of(30));

        $this->assertSame(100, $wallet->balance()->value);
        $this->assertSame(0, $wallet->reserved()->value);
        $this->assertSame(100, $wallet->available()->value);
    }
}

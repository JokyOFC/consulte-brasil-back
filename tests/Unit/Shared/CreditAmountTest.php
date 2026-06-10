<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exception\InvalidArgumentException;
use Src\Shared\Domain\ValueObject\CreditAmount;

final class CreditAmountTest extends TestCase
{
    public function test_it_creates_a_non_negative_amount(): void
    {
        $this->assertSame(10, CreditAmount::of(10)->value);
        $this->assertTrue(CreditAmount::zero()->isZero());
    }

    public function test_it_rejects_negative_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CreditAmount::of(-1);
    }

    public function test_it_adds_and_subtracts(): void
    {
        $result = CreditAmount::of(10)->add(CreditAmount::of(5))->subtract(CreditAmount::of(3));

        $this->assertSame(12, $result->value);
    }

    public function test_it_forbids_subtracting_below_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CreditAmount::of(5)->subtract(CreditAmount::of(6));
    }

    public function test_it_compares_amounts(): void
    {
        $this->assertTrue(CreditAmount::of(10)->isGreaterThanOrEqualTo(CreditAmount::of(10)));
        $this->assertTrue(CreditAmount::of(3)->isLessThan(CreditAmount::of(4)));
        $this->assertFalse(CreditAmount::of(3)->isGreaterThan(CreditAmount::of(4)));
    }
}

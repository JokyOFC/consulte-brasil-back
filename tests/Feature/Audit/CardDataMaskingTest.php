<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use ReflectionMethod;
use Src\Modules\Audit\Infrastructure\Http\Middleware\LogRequest;
use Tests\TestCase;

final class CardDataMaskingTest extends TestCase
{
    public function test_card_fields_are_masked_before_persisting(): void
    {
        $middleware = new LogRequest;
        $redact = new ReflectionMethod($middleware, 'redact');

        $masked = $redact->invoke($middleware, [
            'amount' => 5000,
            'card_number' => '4111111111111111',
            'cvv' => '123',
            'card_token' => 'tok_secret',
            'payer' => [
                'email' => 'a@b.com',
                'security_code' => '999',
            ],
        ]);

        $this->assertSame('***', $masked['card_number']);
        $this->assertSame('***', $masked['cvv']);
        $this->assertSame('***', $masked['card_token']);
        $this->assertSame('***', $masked['payer']['security_code']);

        // Campos não sensíveis permanecem intactos.
        $this->assertSame(5000, $masked['amount']);
        $this->assertSame('a@b.com', $masked['payer']['email']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Audit;

use Src\Modules\Audit\Infrastructure\Http\Support\AuditEncryptedPayloadLimiter;
use Tests\TestCase;

final class AuditEncryptedPayloadLimiterTest extends TestCase
{
    public function test_fits_small_payload_unchanged(): void
    {
        $input = ['method' => 'POST', 'path' => '/api/v1/consult/cpf'];

        $this->assertSame($input, (new AuditEncryptedPayloadLimiter)->fit($input));
    }

    public function test_shortens_individual_long_strings_instead_of_dropping_payload(): void
    {
        $input = [
            'nome' => 'Maria',
            'observacao' => str_repeat('x', 50_000),
        ];

        $result = (new AuditEncryptedPayloadLimiter)->fit($input);

        $this->assertSame('Maria', $result['nome']);
        $this->assertLessThan(10_000, strlen((string) $result['observacao']));
        $this->assertStringEndsWith('…', (string) $result['observacao']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Audit;

use Src\Modules\Audit\Infrastructure\Http\Support\RequestResponseSummarizer;
use Tests\TestCase;

final class RequestResponseSummarizerTest extends TestCase
{
    public function test_strips_heavy_consultation_payload_from_success_response(): void
    {
        $content = json_encode([
            'data' => [
                'consultation_id' => '01932a1b-8c4d-7000-8000-000000000001',
                'provider' => 'cpfcnpj',
                'amount_charged' => 29,
                'from_cache' => false,
                'data' => [
                    'nome' => 'JOAO DA SILVA',
                    'certificadoPdfBase64' => str_repeat('A', 200_000),
                    'raw' => ['nested' => str_repeat('B', 200_000)],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $summary = (new RequestResponseSummarizer)->summarize($content);

        $this->assertSame('01932a1b-8c4d-7000-8000-000000000001', $summary['data']['consultation_id']);
        $this->assertTrue($summary['data']['data']['_omitted']);
        $this->assertSame(['nome', 'certificadoPdfBase64', 'raw'], $summary['data']['data']['fields']);
        $this->assertArrayNotHasKey('certificadoPdfBase64', $summary['data']['data']);
    }

    public function test_keeps_error_envelope(): void
    {
        $content = json_encode([
            'error' => [
                'type' => 'all_providers_failed',
                'message' => 'Falha nos provedores.',
            ],
        ], JSON_THROW_ON_ERROR);

        $summary = (new RequestResponseSummarizer)->summarize($content);

        $this->assertSame('all_providers_failed', $summary['error']['type']);
    }

    public function test_summary_stays_under_size_limit(): void
    {
        $content = json_encode([
            'data' => [
                'consultation_id' => 'abc',
                'data' => array_fill_keys(range(1, 500), str_repeat('x', 500)),
            ],
        ], JSON_THROW_ON_ERROR);

        $summary = (new RequestResponseSummarizer)->summarize($content);

        $this->assertLessThanOrEqual(48_000, strlen(json_encode($summary, JSON_THROW_ON_ERROR)));
    }
}

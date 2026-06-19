<?php

declare(strict_types=1);

namespace Tests\Unit\Audit;

use Src\Modules\Audit\Infrastructure\Http\Support\RequestResponseSummarizer;
use Tests\TestCase;

final class RequestResponseSummarizerTest extends TestCase
{
    public function test_keeps_consultation_fields_but_strips_pdf_and_raw(): void
    {
        $content = json_encode([
            'data' => [
                'consultation_id' => '01932a1b-8c4d-7000-8000-000000000001',
                'provider' => 'cpfcnpj',
                'amount_charged' => 161,
                'from_cache' => false,
                'data' => [
                    'cpf' => '11144477735',
                    'nome' => 'JOAO DA SILVA',
                    'certificadoPdfBase64' => str_repeat('A', 200_000),
                    'raw' => ['nested' => str_repeat('B', 200_000)],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $summary = (new RequestResponseSummarizer)->summarize($content);

        $this->assertSame('JOAO DA SILVA', $summary['data']['data']['nome']);
        $this->assertSame('11144477735', $summary['data']['data']['cpf']);
        $this->assertArrayNotHasKey('certificadoPdfBase64', $summary['data']['data']);
        $this->assertArrayNotHasKey('raw', $summary['data']['data']);
        $this->assertArrayNotHasKey('_omitted', $summary['data']['data']);
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

    public function test_does_not_mutate_original_decoded_payload(): void
    {
        $original = [
            'data' => [
                'consultation_id' => 'abc',
                'data' => [
                    'nome' => 'Maria',
                    'raw' => ['x' => str_repeat('z', 10_000)],
                ],
            ],
        ];

        $content = json_encode($original, JSON_THROW_ON_ERROR);
        (new RequestResponseSummarizer)->summarize($content);

        $this->assertArrayHasKey('raw', $original['data']['data']);
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

        $this->assertLessThanOrEqual(120_000, strlen(json_encode($summary, JSON_THROW_ON_ERROR)));
    }
}

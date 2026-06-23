<?php

declare(strict_types=1);

namespace Tests\Unit\Audit;

use Tests\TestCase;
use Src\Modules\Audit\Infrastructure\Http\Support\RequestLogPresenter;
use Src\Modules\Audit\Infrastructure\Persistence\Eloquent\Models\RequestLogModel;

final class RequestLogPresenterTest extends TestCase
{
    public function test_extracts_query_type_and_error_from_consult_response(): void
    {
        $log = new RequestLogModel([
            'id' => 'log-1',
            'method' => 'POST',
            'path' => '/api/v1/consult/cpf_analise_credito_basic',
            'status_code' => 503,
            'success' => false,
            'duration_ms' => 10229,
            'response' => [
                'error' => [
                    'type' => 'all_providers_failed',
                    'message' => 'All providers failed for query type [cpf_analise_credito_basic].',
                ],
            ],
        ]);

        $presented = (new RequestLogPresenter)->present($log);

        $this->assertSame('cpf_analise_credito_basic', $presented['query_type']);
        $this->assertSame('all_providers_failed', $presented['error_type']);
        $this->assertStringContainsString('All providers failed', (string) $presented['error_message']);
    }

    public function test_extracts_success_metadata_from_response(): void
    {
        $log = new RequestLogModel([
            'id' => 'log-2',
            'method' => 'POST',
            'path' => '/api/v1/consult/cpf',
            'status_code' => 200,
            'success' => true,
            'response' => [
                'data' => [
                    'consultation_id' => '019abc',
                    'provider' => 'api_brasil',
                    'from_cache' => true,
                    'amount_charged' => 1107,
                ],
            ],
        ]);

        $presented = (new RequestLogPresenter)->present($log);

        $this->assertSame('cpf', $presented['query_type']);
        $this->assertTrue($presented['from_cache']);
        $this->assertSame('api_brasil', $presented['provider']);
        $this->assertSame(1107, $presented['amount_charged']);
        $this->assertSame('019abc', $presented['consultation_id']);
    }
}

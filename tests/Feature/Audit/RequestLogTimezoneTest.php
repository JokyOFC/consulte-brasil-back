<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Support\Dates;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Src\Modules\Audit\Infrastructure\Persistence\Eloquent\Models\RequestLogModel;
use Tests\TestCase;

final class RequestLogTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_log_created_at_serializes_in_brasilia(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);

        Carbon::setTestNow(Carbon::parse('2026-06-22 23:37:28', 'America/Sao_Paulo'));

        RequestLogModel::query()->create([
            'id' => (string) Str::uuid(),
            'method' => 'POST',
            'path' => '/api/v1/consult/cpf',
            'success' => true,
            'status_code' => 200,
            'created_at' => now(),
        ]);

        $log = RequestLogModel::query()->firstOrFail();
        $iso = Dates::toFrontendIso($log->created_at);

        $this->assertStringContainsString('2026-06-22T23:37:28', $iso);
    }
}

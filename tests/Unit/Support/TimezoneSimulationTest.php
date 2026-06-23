<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Casts\UtcDatetime;
use App\Support\Dates;
use Src\Modules\Audit\Infrastructure\Persistence\Eloquent\Models\RequestLogModel;
use Tests\TestCase;

final class TimezoneSimulationTest extends TestCase
{
    public function test_mysql_timestamp_string_in_sao_paulo_session_is_not_misread_as_utc(): void
    {
        config([
            'app.timezone' => 'UTC',
            'app.display_timezone' => 'America/Sao_Paulo',
            'database.connections.sqlite.timezone' => '-03:00',
        ]);

        $cast = new UtcDatetime;
        $model = new RequestLogModel;

        // MySQL TIMESTAMP com sessão -03:00 devolve horário local de Brasília.
        $carbon = $cast->get($model, 'created_at', '2026-06-22 23:37:28', []);

        $this->assertSame(
            '2026-06-22T23:37:28-03:00',
            Dates::toFrontendIso($carbon),
        );
    }

    public function test_mysql_timestamp_string_in_utc_session_serializes_to_brasilia(): void
    {
        config([
            'app.timezone' => 'UTC',
            'app.display_timezone' => 'America/Sao_Paulo',
            'database.connections.sqlite.timezone' => '+00:00',
        ]);

        $cast = new UtcDatetime;
        $model = new RequestLogModel;

        $carbon = $cast->get($model, 'created_at', '2026-06-23 02:37:28', []);

        $this->assertSame(
            '2026-06-22T23:37:28-03:00',
            Dates::toFrontendIso($carbon),
        );
    }
}

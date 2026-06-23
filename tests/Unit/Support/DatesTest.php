<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Dates;
use Carbon\Carbon;
use Tests\TestCase;

final class DatesTest extends TestCase
{
    public function test_raw_mysql_utc_string_is_converted_to_brasilia_even_when_app_timezone_is_utc(): void
    {
        config([
            'app.timezone' => 'UTC',
            'app.display_timezone' => 'America/Sao_Paulo',
            'database.connections.sqlite.timezone' => '+00:00',
        ]);

        $iso = Dates::toFrontendIso('2026-06-23 02:37:28');

        $this->assertSame('2026-06-22T23:37:28-03:00', $iso);
    }

    public function test_datetime_interface_normalizes_via_utc_instant(): void
    {
        config([
            'app.timezone' => 'UTC',
            'app.display_timezone' => 'America/Sao_Paulo',
            'database.connections.sqlite.timezone' => '+00:00',
        ]);

        $instant = Carbon::parse('2026-06-23 02:37:28', 'UTC');

        $this->assertSame('2026-06-22T23:37:28-03:00', Dates::toFrontendIso($instant));
    }

    public function test_iso_without_offset_is_treated_as_utc_on_frontend_parse(): void
    {
        config([
            'app.display_timezone' => 'America/Sao_Paulo',
            'database.connections.sqlite.timezone' => '+00:00',
        ]);

        $iso = Dates::toFrontendIso('2026-06-23 02:37:28');

        $this->assertStringContainsString('2026-06-22T23:37:28', $iso);
    }

    public function test_raw_mysql_string_in_sao_paulo_session_serializes_to_brasilia(): void
    {
        config([
            'app.display_timezone' => 'America/Sao_Paulo',
            'database.connections.sqlite.timezone' => '-03:00',
        ]);

        $this->assertSame(
            '2026-06-22T23:37:28-03:00',
            Dates::toFrontendIso('2026-06-22 23:37:28'),
        );
    }
}

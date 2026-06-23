<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Dates;
use Carbon\Carbon;
use Tests\TestCase;

final class DatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'America/Sao_Paulo']);
    }

    public function test_raw_mysql_utc_string_is_converted_to_brasilia_iso(): void
    {
        $iso = Dates::toFrontendIso('2026-06-23 02:37:28');

        $this->assertSame(
            Carbon::parse('2026-06-23 02:37:28', 'UTC')
                ->timezone('America/Sao_Paulo')
                ->toIso8601String(),
            $iso,
        );
    }

    public function test_datetime_interface_uses_app_timezone(): void
    {
        $instant = Carbon::parse('2026-06-23 02:37:28', 'UTC');

        $iso = Dates::toFrontendIso($instant);

        $this->assertStringContainsString('-03:00', $iso);
        $this->assertStringContainsString('T23:37:28', $iso);
    }
}

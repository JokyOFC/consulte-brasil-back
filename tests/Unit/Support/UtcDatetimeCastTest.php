<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Casts\UtcDatetime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

final class UtcDatetimeCastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'America/Sao_Paulo']);
    }

    public function test_reads_utc_database_value_as_brasilia(): void
    {
        $cast = new UtcDatetime;
        $model = new class extends Model {};

        /** @var Carbon $value */
        $value = $cast->get($model, 'created_at', '2026-06-23 02:37:28', []);

        $this->assertSame('2026-06-22 23:37:28', $value->format('Y-m-d H:i:s'));
        $this->assertSame('America/Sao_Paulo', $value->timezone->getName());
    }

    public function test_writes_local_input_as_utc_string(): void
    {
        $cast = new UtcDatetime;
        $model = new class extends Model {};

        $stored = $cast->set(
            $model,
            'created_at',
            Carbon::parse('2026-06-22 23:37:28', 'America/Sao_Paulo'),
            [],
        );

        $this->assertSame('2026-06-23 02:37:28', $stored);
    }
}

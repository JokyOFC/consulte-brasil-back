<?php

declare(strict_types=1);

namespace Tests\Feature\Hardening;

use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models\ConsultationModel;
use Src\Shared\Application\Contracts\IdGenerator;
use Tests\TestCase;

final class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_provision_admin_plans_query_types_and_provider(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', ['email' => 'admin@consultebrasil.test', 'role' => 'admin']);
        $this->assertDatabaseHas('plans', ['slug' => 'starter']);
        $this->assertDatabaseHas('plans', ['slug' => 'growth']);
        $this->assertDatabaseHas('query_types', ['code' => 'cpf']);
        $this->assertDatabaseHas('providers', ['identifier' => 'api_brasil']);

        $providerId = DB::table('providers')->where('identifier', 'api_brasil')->value('id');
        $this->assertDatabaseHas('provider_capabilities', [
            'provider_id' => $providerId,
            'query_type' => 'cpf',
            'enabled' => 1,
        ]);
    }

    public function test_seeders_are_idempotent(): void
    {
        $counts = static fn (): array => [
            'plans' => DB::table('plans')->count(),
            'query_types' => DB::table('query_types')->count(),
            'providers' => DB::table('providers')->count(),
            'provider_capabilities' => DB::table('provider_capabilities')->count(),
        ];

        $this->seed();
        $afterFirst = $counts();

        $this->seed(); // executar de novo não duplica
        $afterSecond = $counts();

        $this->assertSame($afterFirst, $afterSecond);
        // api_brasil + cpfcnpj + mercado_pago (gateway de pagamento).
        $this->assertSame(3, $afterSecond['providers']);
        // cpf/cnpj + demais pacotes do catálogo CPF.CNPJ.
        $this->assertGreaterThan(2, $afterSecond['query_types']);
    }

    public function test_purge_request_hash_anonymizes_old_consultations_only(): void
    {
        $ids = app(IdGenerator::class);

        $insert = function (string $hash, CarbonInterface $createdAt) use ($ids): string {
            $id = $ids->generate();
            ConsultationModel::query()->insert([
                'id' => $id,
                'account_id' => $ids->generate(),
                'query_type' => 'cpf',
                'status' => 'success',
                'credit_cost' => 1,
                'reservation_id' => $ids->generate(),
                'request_hash' => $hash,
                'created_at' => $createdAt,
            ]);

            return $id;
        };

        $oldId = $insert(str_repeat('a', 64), now()->subDays(200));
        $recentId = $insert(str_repeat('b', 64), now()->subDays(10));

        $this->artisan('consultation:purge-request-hash', ['--days' => 180])->assertExitCode(0);

        $this->assertSame(str_repeat('0', 64), ConsultationModel::find($oldId)->request_hash);
        $this->assertSame(str_repeat('b', 64), ConsultationModel::find($recentId)->request_hash);
    }
}

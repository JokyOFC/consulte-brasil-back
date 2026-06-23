<?php

declare(strict_types=1);

namespace Tests\Feature\Consultation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Modules\Billing\Application\DTO\GrantCreditsInput;
use Src\Modules\Billing\Application\UseCase\GrantCredits;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Consultation\Application\DTO\ExecuteConsultationInput;
use Src\Modules\Consultation\Application\UseCase\ExecuteConsultation;
use Src\Modules\Consultation\Domain\Entity\Consultation;
use Src\Modules\Consultation\Domain\Exception\AllProvidersFailed;
use Src\Modules\Consultation\Domain\Exception\NoProviderAvailable;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Modules\Provider\Domain\Entity\Provider;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;
use Src\Modules\Provider\Domain\ValueObject\ProviderStatus;
use Src\Shared\Application\Contracts\IdGenerator;
use Tests\Support\Consultation\FakeProvider;
use Tests\TestCase;

final class ExecuteConsultationTest extends TestCase
{
    use RefreshDatabase;

    private function seedAccount(int $credits = 100): string
    {
        $account = app(CreateAccount::class)->handle(
            new CreateAccountInput('ACME', '11.222.333/0001-81')
        );

        if ($credits > 0) {
            app(GrantCredits::class)->handle(new GrantCreditsInput($account->id->value, $credits));
        }

        return $account->id->value;
    }

    private function seedQueryType(string $code = 'cpf', int $cost = 1, ?int $cacheTtlSeconds = 3600): void
    {
        DB::table('query_types')->insert([
            'id' => app(IdGenerator::class)->generate(),
            'code' => $code,
            'name' => strtoupper($code),
            'default_credit_cost' => $cost,
            'cache_ttl_seconds' => $cacheTtlSeconds,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedProvider(string $identifier, int $priority = 10, string $queryType = 'cpf', int $priceCents = 1): string
    {
        $providerId = app(IdGenerator::class)->generate();

        app(ProviderRepository::class)->save(new Provider(
            id: $providerId,
            identifier: $identifier,
            name: $identifier,
            status: ProviderStatus::Enabled,
            baseUrl: null,
            credentials: [],
        ));

        DB::table('provider_capabilities')->insert([
            'id' => app(IdGenerator::class)->generate(),
            'provider_id' => $providerId,
            'query_type' => $queryType,
            'priority' => $priority,
            'price_cents' => $priceCents,
            'enabled' => true,
            'config' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $providerId;
    }

    private function tagProvider(FakeProvider $instance): void
    {
        $this->app->instance(get_class($instance).':'.$instance->identifier(), $instance);
        $this->app->tag([get_class($instance).':'.$instance->identifier()], 'consultation.provider');
    }

    public function test_happy_path_charges_credits_and_logs_consultation(): void
    {
        $accountId = $this->seedAccount(credits: 10);
        $this->seedQueryType('cpf', cost: 3);
        $this->seedProvider('alpha', priceCents: 3);
        $this->tagProvider(new FakeProvider('alpha', payload: ['name' => 'João']));

        $output = app(ExecuteConsultation::class)->handle(
            new ExecuteConsultationInput($accountId, null, 'cpf', ['document' => '11144477735'])
        );

        $this->assertSame('alpha', $output->providerIdentifier);
        $this->assertSame(3, $output->creditsCharged);
        $this->assertSame(['name' => 'João'], $output->data);

        $wallet = app(WalletRepository::class)->findByAccountId($accountId);
        $this->assertSame(7, $wallet->balance()->value);
        $this->assertSame(0, $wallet->reserved()->value);

        $this->assertDatabaseHas('consultations', [
            'id' => $output->consultationId,
            'status' => Consultation::STATUS_SUCCESS,
            'query_type' => 'cpf',
        ]);
    }

    public function test_failover_skips_failing_provider_and_succeeds_on_next(): void
    {
        $accountId = $this->seedAccount(credits: 10);
        $this->seedQueryType('cpf', cost: 2);
        $this->seedProvider('alpha', priority: 10, priceCents: 2);
        $this->seedProvider('beta', priority: 20, priceCents: 2);

        $alpha = new FakeProvider('alpha', shouldFail: true);
        $beta = new FakeProvider('beta', payload: ['name' => 'OK']);
        $this->tagProvider($alpha);
        $this->tagProvider($beta);

        $output = app(ExecuteConsultation::class)->handle(
            new ExecuteConsultationInput($accountId, null, 'cpf', ['document' => '11144477735'])
        );

        $this->assertSame('beta', $output->providerIdentifier);
        $this->assertSame(1, $alpha->callCount);
        $this->assertSame(1, $beta->callCount);
        $this->assertSame(8, app(WalletRepository::class)->findByAccountId($accountId)->balance()->value);
    }

    public function test_when_all_providers_fail_credits_are_refunded(): void
    {
        $accountId = $this->seedAccount(credits: 5);
        $this->seedQueryType('cpf', cost: 2);
        $this->seedProvider('alpha', priority: 10, priceCents: 2);
        $this->seedProvider('beta', priority: 20, priceCents: 2);

        $this->tagProvider(new FakeProvider('alpha', shouldFail: true));
        $this->tagProvider(new FakeProvider('beta', shouldFail: true));

        try {
            app(ExecuteConsultation::class)->handle(
                new ExecuteConsultationInput($accountId, null, 'cpf', ['document' => '11144477735'])
            );
            $this->fail('AllProvidersFailed expected.');
        } catch (AllProvidersFailed) {
            // esperado
        }

        $wallet = app(WalletRepository::class)->findByAccountId($accountId);
        $this->assertSame(5, $wallet->balance()->value, 'saldo deve permanecer (estorno)');
        $this->assertSame(0, $wallet->reserved()->value, 'reserva deve ser liberada');

        $this->assertDatabaseHas('consultations', [
            'account_id' => $accountId,
            'status' => Consultation::STATUS_REFUNDED,
        ]);
    }

    public function test_no_enabled_provider_yields_unavailable(): void
    {
        $accountId = $this->seedAccount(credits: 5);
        $this->seedQueryType('cpf', cost: 1);

        $this->expectException(NoProviderAvailable::class);

        app(ExecuteConsultation::class)->handle(
            new ExecuteConsultationInput($accountId, null, 'cpf', [])
        );
    }

    public function test_second_identical_request_is_served_from_cache(): void
    {
        $accountId = $this->seedAccount(credits: 10);
        $this->seedQueryType('cpf', cost: 3, cacheTtlSeconds: 3600);
        $this->seedProvider('alpha', priceCents: 3);

        $provider = new FakeProvider('alpha', payload: ['name' => 'João']);
        $this->tagProvider($provider);

        $input = new ExecuteConsultationInput($accountId, null, 'cpf', ['document' => '11144477735']);

        $first = app(ExecuteConsultation::class)->handle($input);
        $second = app(ExecuteConsultation::class)->handle($input);

        $this->assertFalse($first->fromCache);
        $this->assertTrue($second->fromCache);
        $this->assertSame(['name' => 'João'], $second->data);
        $this->assertSame(1, $provider->callCount);
        $this->assertSame(4, app(WalletRepository::class)->findByAccountId($accountId)->balance()->value);
    }

    public function test_cache_can_be_disabled(): void
    {
        $accountId = $this->seedAccount(credits: 10);
        $this->seedQueryType('cpf', cost: 3, cacheTtlSeconds: 0);
        $this->app->forgetInstance(\Src\Modules\Consultation\Application\Port\ConsultationResultCache::class);
        $this->seedProvider('alpha', priceCents: 3);

        $provider = new FakeProvider('alpha', payload: ['name' => 'João']);
        $this->tagProvider($provider);

        $input = new ExecuteConsultationInput($accountId, null, 'cpf', ['document' => '11144477735']);

        app(ExecuteConsultation::class)->handle($input);
        $output = app(ExecuteConsultation::class)->handle($input);

        $this->assertFalse($output->fromCache);
        $this->assertSame(2, $provider->callCount);
    }

    public function test_formatted_document_hits_cache_from_digits_only_request(): void
    {
        $accountId = $this->seedAccount(credits: 10);
        $this->seedQueryType('cpf', cost: 3, cacheTtlSeconds: 3600);
        $this->seedProvider('alpha', priceCents: 3);

        $provider = new FakeProvider('alpha', payload: ['name' => 'João']);
        $this->tagProvider($provider);

        app(ExecuteConsultation::class)->handle(
            new ExecuteConsultationInput($accountId, null, 'cpf', ['document' => '11144477735']),
        );

        $cached = app(ExecuteConsultation::class)->handle(
            new ExecuteConsultationInput($accountId, null, 'cpf', ['document' => '111.444.777-35']),
        );

        $this->assertTrue($cached->fromCache);
        $this->assertSame(1, $provider->callCount);
    }

    public function test_cache_is_invalidated_automatically_when_query_type_ttl_changes(): void
    {
        $accountId = $this->seedAccount(credits: 20);
        $this->seedQueryType('cpf', cost: 3, cacheTtlSeconds: 3600);
        $this->seedProvider('alpha', priceCents: 3);

        $provider = new FakeProvider('alpha', payload: ['name' => 'João']);
        $this->tagProvider($provider);

        $input = new ExecuteConsultationInput($accountId, null, 'cpf', ['document' => '11144477735']);

        app(ExecuteConsultation::class)->handle($input);
        $cached = app(ExecuteConsultation::class)->handle($input);
        $this->assertTrue($cached->fromCache);
        $this->assertSame(1, $provider->callCount);

        $queryType = \Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models\QueryTypeModel::query()
            ->where('code', 'cpf')
            ->firstOrFail();
        $queryType->cache_ttl_seconds = 7200;
        $queryType->save();

        $afterInvalidation = app(ExecuteConsultation::class)->handle($input);
        $this->assertFalse($afterInvalidation->fromCache);
        $this->assertSame(2, $provider->callCount);
    }
}

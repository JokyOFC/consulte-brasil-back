<?php

declare(strict_types=1);

namespace Tests\Feature\Consultation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Src\Modules\Billing\Application\DTO\GrantCreditsInput;
use Src\Modules\Billing\Application\UseCase\GrantCredits;
use Src\Modules\Consultation\Application\DTO\ExecuteConsultationInput;
use Src\Modules\Consultation\Application\UseCase\ExecuteConsultation;
use Src\Modules\Consultation\Domain\Exception\AllProvidersFailed;
use Src\Modules\Consultation\Infrastructure\Jobs\DeliverConsultationWebhookJob;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;
use Src\Modules\Provider\Domain\Entity\Provider;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;
use Src\Modules\Provider\Domain\ValueObject\ProviderStatus;
use Src\Shared\Application\Contracts\IdGenerator;
use Tests\Support\Consultation\FakeProvider;
use Tests\TestCase;

final class ConsultationWebhookDeliveryTest extends TestCase
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

    private function configureWebhook(string $accountId, ?string $url = 'https://example.com/webhook'): void
    {
        AccountModel::query()->whereKey($accountId)->update([
            'webhook_url' => $url,
            'webhook_secret' => $url !== null ? encrypt('whsec_test') : null,
        ]);
    }

    private function seedQueryType(string $code = 'cpf', int $cost = 1): void
    {
        DB::table('query_types')->insert([
            'id' => app(IdGenerator::class)->generate(),
            'code' => $code,
            'name' => strtoupper($code),
            'default_credit_cost' => $cost,
            'cache_ttl_seconds' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedProvider(string $identifier, int $priority = 10, string $queryType = 'cpf', int $priceCents = 1): void
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
    }

    private function tagProvider(FakeProvider $instance): void
    {
        $this->app->instance(get_class($instance).':'.$instance->identifier(), $instance);
        $this->app->tag([get_class($instance).':'.$instance->identifier()], 'consultation.provider');
    }

    public function test_dispatches_job_when_webhook_is_configured(): void
    {
        Queue::fake();

        $accountId = $this->seedAccount();
        $this->configureWebhook($accountId);
        $this->seedQueryType('cpf', cost: 2);
        $this->seedProvider('alpha', priceCents: 2);
        $this->tagProvider(new FakeProvider('alpha', payload: ['name' => 'João']));

        app(ExecuteConsultation::class)->handle(
            new ExecuteConsultationInput($accountId, null, 'cpf', ['document' => '11144477735'])
        );

        Queue::assertPushed(DeliverConsultationWebhookJob::class, function (DeliverConsultationWebhookJob $job): bool {
            return $job->webhookUrl === 'https://example.com/webhook'
                && $job->webhookSecret === 'whsec_test'
                && $job->payload['event'] === 'consultation.completed'
                && $job->payload['status'] === 'success'
                && $job->payload['query_type'] === 'cpf'
                && $job->payload['data'] === ['name' => 'João'];
        });
    }

    public function test_skips_job_when_webhook_is_not_configured(): void
    {
        Queue::fake();

        $accountId = $this->seedAccount();
        $this->seedQueryType('cpf', cost: 2);
        $this->seedProvider('alpha', priceCents: 2);
        $this->tagProvider(new FakeProvider('alpha', payload: ['name' => 'João']));

        app(ExecuteConsultation::class)->handle(
            new ExecuteConsultationInput($accountId, null, 'cpf', ['document' => '11144477735'])
        );

        Queue::assertNothingPushed();
    }

    public function test_dispatches_refunded_payload_when_all_providers_fail(): void
    {
        Queue::fake();

        $accountId = $this->seedAccount();
        $this->configureWebhook($accountId);
        $this->seedQueryType('cpf', cost: 2);
        $this->seedProvider('alpha', priceCents: 2);
        $this->tagProvider(new FakeProvider('alpha', shouldFail: true));

        try {
            app(ExecuteConsultation::class)->handle(
                new ExecuteConsultationInput($accountId, null, 'cpf', ['document' => '11144477735'])
            );
            $this->fail('AllProvidersFailed expected.');
        } catch (AllProvidersFailed) {
            // esperado
        }

        Queue::assertPushed(DeliverConsultationWebhookJob::class, function (DeliverConsultationWebhookJob $job): bool {
            return $job->payload['status'] === 'refunded'
                && ($job->payload['error']['code'] ?? null) === 'all_providers_failed'
                && ! array_key_exists('data', $job->payload);
        });
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Consultation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Modules\Billing\Application\DTO\GrantCreditsInput;
use Src\Modules\Billing\Application\UseCase\GrantCredits;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Modules\Provider\Domain\Entity\Provider;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;
use Src\Modules\Provider\Domain\ValueObject\ProviderStatus;
use Src\Shared\Application\Contracts\IdGenerator;
use Tests\Support\Consultation\FakeProvider;
use Tests\TestCase;

final class ClientConsultationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_consultations_page(): void
    {
        $this->get(route('client.consultations.index'))
            ->assertRedirect(route('login'));
    }

    public function test_client_can_view_consultations_catalog(): void
    {
        ['user' => $user] = $this->provisionClient(credits: 500);
        $this->seedQueryType('cpf', cost: 29);
        $this->seedProvider('alpha', 'cpf', priceCents: 29);

        $this->actingAs($user)
            ->get(route('client.consultations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('client/consultations/index')
                ->has('query_types', 1)
                ->where('query_types.0.code', 'cpf')
                ->where('query_types.0.price_cents', 29));
    }

    public function test_client_can_execute_consultation_from_web_panel(): void
    {
        ['user' => $user, 'accountId' => $accountId] = $this->provisionClient(credits: 500);
        $this->seedQueryType('cpf', cost: 29);
        $this->seedProvider('alpha', 'cpf', priceCents: 29);
        $this->tagProvider(new FakeProvider('alpha', payload: ['name' => 'Maria Silva']));

        $this->actingAs($user)
            ->post(route('client.consultations.store', ['queryType' => 'cpf']), [
                'document' => '111.444.777-35',
            ])
            ->assertRedirect(route('client.consultations.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('consultation_result');

        $this->assertDatabaseHas('consultations', [
            'account_id' => $accountId,
            'query_type' => 'cpf',
            'status' => 'success',
        ]);
    }

    public function test_web_consultation_returns_error_when_insufficient_balance(): void
    {
        ['user' => $user] = $this->provisionClient(credits: 10);
        $this->seedQueryType('cpf', cost: 29);
        $this->seedProvider('alpha', 'cpf', priceCents: 29);
        $this->tagProvider(new FakeProvider('alpha'));

        $this->actingAs($user)
            ->post(route('client.consultations.store', ['queryType' => 'cpf']), [
                'document' => '11144477735',
            ])
            ->assertRedirect(route('client.consultations.index'))
            ->assertSessionHas('error');
    }

    /** @return array{user: User, accountId: string} */
    private function provisionClient(int $credits): array
    {
        $account = app(CreateAccount::class)->handle(
            new CreateAccountInput('ACME', '11.222.333/0001-81')
        );

        if ($credits > 0) {
            app(GrantCredits::class)->handle(new GrantCreditsInput($account->id->value, $credits));
        }

        $user = User::factory()->create([
            'role' => 'client',
            'account_id' => $account->id->value,
        ]);

        return ['user' => $user, 'accountId' => $account->id->value];
    }

    private function seedQueryType(string $code, int $cost): void
    {
        DB::table('query_types')->insert([
            'id' => app(IdGenerator::class)->generate(),
            'code' => $code,
            'name' => strtoupper($code),
            'default_credit_cost' => $cost,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedProvider(string $identifier, string $queryType, int $priceCents = 1): void
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
            'priority' => 10,
            'price_cents' => $priceCents,
            'enabled' => true,
            'config' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function tagProvider(FakeProvider $instance): void
    {
        $key = get_class($instance).':'.$instance->identifier();
        $this->app->instance($key, $instance);
        $this->app->tag([$key], 'consultation.provider');
    }
}

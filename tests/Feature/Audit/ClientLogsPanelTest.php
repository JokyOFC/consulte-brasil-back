<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\User;
use Database\Seeders\QueryTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Modules\Audit\Infrastructure\Persistence\Eloquent\Models\RequestLogModel;
use Src\Modules\Billing\Application\DTO\GrantCreditsInput;
use Src\Modules\Billing\Application\UseCase\GrantCredits;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\DTO\IssueApiKeyInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Modules\Identity\Application\UseCase\IssueApiKey;
use Tests\TestCase;

final class ClientLogsPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_request_appears_in_client_logs_panel(): void
    {
        $account = app(CreateAccount::class)->handle(new CreateAccountInput('Logs Co', '11.222.333/0001-81'));
        app(GrantCredits::class)->handle(new GrantCreditsInput($account->id->value, 100));
        $issued = app(IssueApiKey::class)->handle(new IssueApiKeyInput($account->id->value, 'panel-test'));

        $user = User::factory()->create(['account_id' => $account->id->value]);

        $this->withToken($issued->plainToken)->getJson('/api/v1/me')->assertOk();

        $this->actingAs($user)
            ->get(route('client.logs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('client/logs/index')
                ->has('logs.data', 1)
                ->where('logs.data.0.path', '/api/v1/me')
                ->where('logs.data.0.success', true));

        $this->assertSame(1, RequestLogModel::query()->where('account_id', $account->id->value)->count());
    }
}

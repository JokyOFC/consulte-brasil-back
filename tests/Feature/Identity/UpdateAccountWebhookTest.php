<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\DTO\IssueApiKeyInput;
use Src\Modules\Identity\Application\DTO\UpdateAccountWebhookInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Modules\Identity\Application\UseCase\IssueApiKey;
use Src\Modules\Identity\Application\UseCase\UpdateAccountWebhook;
use Src\Modules\Identity\Domain\Exception\InvalidWebhookUrl;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;
use Tests\TestCase;

final class UpdateAccountWebhookTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{accountId: string, token: string} */
    private function provisionClient(): array
    {
        $account = app(CreateAccount::class)->handle(
            new CreateAccountInput('ACME', '11.222.333/0001-81')
        );

        $issued = app(IssueApiKey::class)->handle(
            new IssueApiKeyInput($account->id->value, 'integration')
        );

        return [
            'accountId' => $account->id->value,
            'token' => $issued->plainToken,
        ];
    }

    public function test_saves_webhook_url_and_generates_secret(): void
    {
        ['accountId' => $accountId] = $this->provisionClient();

        $output = app(UpdateAccountWebhook::class)->handle(new UpdateAccountWebhookInput(
            accountId: $accountId,
            webhookUrl: 'https://example.com/hooks/consulte',
        ));

        $this->assertTrue($output->webhookConfigured);
        $this->assertSame('https://example.com/hooks/consulte', $output->webhookUrl);
        $this->assertNotNull($output->plainSecret);

        $account = AccountModel::query()->findOrFail($accountId);
        $this->assertSame('https://example.com/hooks/consulte', $account->webhook_url);
        $this->assertSame($output->plainSecret, decrypt($account->webhook_secret));
    }

    public function test_removing_webhook_clears_url_and_secret(): void
    {
        ['accountId' => $accountId] = $this->provisionClient();
        $update = app(UpdateAccountWebhook::class);

        $update->handle(new UpdateAccountWebhookInput(
            accountId: $accountId,
            webhookUrl: 'https://example.com/hooks/consulte',
        ));

        $output = $update->handle(new UpdateAccountWebhookInput(
            accountId: $accountId,
            webhookUrl: null,
        ));

        $this->assertFalse($output->webhookConfigured);
        $this->assertNull($output->webhookUrl);

        $account = AccountModel::query()->findOrFail($accountId);
        $this->assertNull($account->webhook_url);
        $this->assertNull($account->webhook_secret);
    }

    public function test_rejects_invalid_webhook_url(): void
    {
        ['accountId' => $accountId] = $this->provisionClient();

        $this->expectException(InvalidWebhookUrl::class);

        app(UpdateAccountWebhook::class)->handle(new UpdateAccountWebhookInput(
            accountId: $accountId,
            webhookUrl: 'not-a-url',
        ));
    }

    public function test_regenerates_secret_without_changing_url(): void
    {
        ['accountId' => $accountId] = $this->provisionClient();
        $update = app(UpdateAccountWebhook::class);

        $first = $update->handle(new UpdateAccountWebhookInput(
            accountId: $accountId,
            webhookUrl: 'https://example.com/hooks/consulte',
        ));

        $second = $update->handle(new UpdateAccountWebhookInput(
            accountId: $accountId,
            webhookUrl: 'https://example.com/hooks/consulte',
            regenerateSecret: true,
        ));

        $this->assertNotSame($first->plainSecret, $second->plainSecret);
    }

    public function test_api_can_read_and_update_webhook_config(): void
    {
        ['token' => $token] = $this->provisionClient();

        $this->withToken($token)
            ->getJson('/api/v1/webhook')
            ->assertOk()
            ->assertJsonPath('data.webhook_configured', false);

        $this->withToken($token)
            ->putJson('/api/v1/webhook', [
                'webhook_url' => 'https://example.com/hooks/consulte',
            ])
            ->assertOk()
            ->assertJsonPath('data.webhook_configured', true)
            ->assertJsonPath('data.webhook_url', 'https://example.com/hooks/consulte')
            ->assertJsonStructure(['data' => ['webhook_secret']]);
    }
}

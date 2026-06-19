<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Application\UseCase;

use Illuminate\Support\Str;
use Src\Modules\Identity\Application\DTO\UpdateAccountWebhookInput;
use Src\Modules\Identity\Application\DTO\UpdateAccountWebhookOutput;
use Src\Modules\Identity\Domain\Exception\AccountNotFound;
use Src\Modules\Identity\Domain\Exception\InvalidWebhookUrl;
use Src\Modules\Identity\Domain\Repository\AccountRepository;
use Src\Modules\Identity\Domain\ValueObject\AccountId;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;

final readonly class UpdateAccountWebhook
{
    public function __construct(
        private AccountRepository $accounts,
    ) {}

    public function handle(UpdateAccountWebhookInput $input): UpdateAccountWebhookOutput
    {
        $accountId = new AccountId($input->accountId);

        if ($this->accounts->findById($accountId) === null) {
            throw AccountNotFound::withId($accountId);
        }

        $model = AccountModel::query()->findOrFail($input->accountId);
        $plainSecret = null;

        $url = $input->webhookUrl !== null ? trim($input->webhookUrl) : null;
        if ($url === '') {
            $url = null;
        }

        if ($url === null) {
            $model->webhook_url = null;
            $model->webhook_secret = null;
            $model->save();

            return new UpdateAccountWebhookOutput(
                webhookUrl: null,
                webhookConfigured: false,
            );
        }

        $this->assertValidUrl($url);

        $needsSecret = $model->webhook_secret === null || $input->regenerateSecret;

        if ($needsSecret) {
            $plainSecret = Str::random(32);
            $model->webhook_secret = encrypt($plainSecret);
        }

        $model->webhook_url = $url;
        $model->save();

        return new UpdateAccountWebhookOutput(
            webhookUrl: $url,
            webhookConfigured: true,
            plainSecret: $plainSecret,
        );
    }

    private function assertValidUrl(string $url): void
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw InvalidWebhookUrl::withReason('A URL do webhook é inválida.');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw InvalidWebhookUrl::withReason('A URL do webhook deve usar HTTP ou HTTPS.');
        }

        if (app()->environment('production') && $scheme !== 'https') {
            throw InvalidWebhookUrl::withReason('Em produção, a URL do webhook deve usar HTTPS.');
        }

        if ($scheme === 'http') {
            $host = parse_url($url, PHP_URL_HOST);

            if (! in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)) {
                throw InvalidWebhookUrl::withReason('URLs HTTP são permitidas apenas em localhost.');
            }
        }
    }
}

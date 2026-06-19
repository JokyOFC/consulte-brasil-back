<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Infrastructure\Http\Controllers\Client;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Identity\Application\DTO\UpdateAccountWebhookInput;
use Src\Modules\Identity\Application\UseCase\UpdateAccountWebhook;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Painel do cliente: configuração do webhook de saída para consultas.
 */
final class WebhookClientController
{
    public function index(Request $request): Response|HttpResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (($user->role ?? null) === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        $accountId = $user->account_id;

        if ($accountId === null) {
            return Inertia::render('client/webhook/index', [
                'webhook_url' => null,
                'webhook_secret' => null,
                'webhook_configured' => false,
                'account_missing' => true,
            ]);
        }

        $account = AccountModel::query()->findOrFail($accountId);

        return Inertia::render('client/webhook/index', [
            'webhook_url' => $account->webhook_url,
            'webhook_secret' => $this->resolveWebhookSecret($account),
            'webhook_configured' => $account->webhook_url !== null
                && $account->webhook_url !== ''
                && $account->webhook_secret !== null,
            'account_missing' => false,
        ]);
    }

    public function update(Request $request, UpdateAccountWebhook $update): RedirectResponse
    {
        $accountId = $this->requireClientAccountId($request);

        if ($accountId === null) {
            return $this->accountRequiredRedirect();
        }

        $data = $request->validate([
            'webhook_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $update->handle(new UpdateAccountWebhookInput(
            accountId: $accountId,
            webhookUrl: $data['webhook_url'] ?? null,
        ));

        return redirect()
            ->route('client.webhook.index')
            ->with('success', 'Webhook atualizado.');
    }

    public function regenerateSecret(Request $request, UpdateAccountWebhook $update): RedirectResponse
    {
        $accountId = $this->requireClientAccountId($request);

        if ($accountId === null) {
            return $this->accountRequiredRedirect();
        }

        $account = AccountModel::query()->findOrFail($accountId);

        if ($account->webhook_url === null || $account->webhook_url === '') {
            return redirect()
                ->route('client.webhook.index')
                ->with('error', 'Configure uma URL antes de regenerar o secret.');
        }

        $update->handle(new UpdateAccountWebhookInput(
            accountId: $accountId,
            webhookUrl: $account->webhook_url,
            regenerateSecret: true,
        ));

        return redirect()
            ->route('client.webhook.index')
            ->with('success', 'Secret regenerado.');
    }

    private function requireClientAccountId(Request $request): ?string
    {
        /** @var User $user */
        $user = $request->user();

        if (($user->role ?? null) === 'admin') {
            return null;
        }

        return $user->account_id;
    }

    private function accountRequiredRedirect(): RedirectResponse
    {
        return redirect()
            ->route('client.webhook.index')
            ->with('error', 'Esta área é exclusiva para contas de cliente.');
    }

    private function resolveWebhookSecret(AccountModel $account): ?string
    {
        if ($account->webhook_secret === null || $account->webhook_secret === '') {
            return null;
        }

        try {
            $secret = decrypt($account->webhook_secret);
        } catch (DecryptException) {
            return null;
        }

        return is_string($secret) && $secret !== '' ? $secret : null;
    }
}

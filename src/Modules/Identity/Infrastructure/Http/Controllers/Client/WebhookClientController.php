<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Infrastructure\Http\Controllers\Client;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Identity\Application\DTO\UpdateAccountWebhookInput;
use Src\Modules\Identity\Application\UseCase\UpdateAccountWebhook;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;

/**
 * Painel do cliente: configuração do webhook de saída para consultas.
 */
final class WebhookClientController
{
    public function index(Request $request): Response
    {
        abort_if($request->user()->account_id === null, 422, 'User has no account.');

        $account = AccountModel::query()->findOrFail($request->user()->account_id);

        return Inertia::render('client/webhook/index', [
            'webhook_url' => $account->webhook_url,
            'webhook_configured' => $account->webhook_url !== null
                && $account->webhook_url !== ''
                && $account->webhook_secret !== null,
        ]);
    }

    public function update(Request $request, UpdateAccountWebhook $update): RedirectResponse
    {
        abort_if($request->user()->account_id === null, 422, 'User has no account.');

        $data = $request->validate([
            'webhook_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $output = $update->handle(new UpdateAccountWebhookInput(
            accountId: $request->user()->account_id,
            webhookUrl: $data['webhook_url'] ?? null,
        ));

        $redirect = back()->with('success', 'Webhook atualizado.');

        if ($output->plainSecret !== null) {
            $redirect = $redirect->with('plain_secret', $output->plainSecret);
        }

        return $redirect;
    }

    public function regenerateSecret(Request $request, UpdateAccountWebhook $update): RedirectResponse
    {
        abort_if($request->user()->account_id === null, 422, 'User has no account.');

        $account = AccountModel::query()->findOrFail($request->user()->account_id);

        abort_if($account->webhook_url === null || $account->webhook_url === '', 422, 'Configure uma URL antes de regenerar o secret.');

        $output = $update->handle(new UpdateAccountWebhookInput(
            accountId: $request->user()->account_id,
            webhookUrl: $account->webhook_url,
            regenerateSecret: true,
        ));

        return back()
            ->with('success', 'Secret regenerado.')
            ->with('plain_secret', $output->plainSecret);
    }
}

<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Infrastructure\Http\Controllers\Client;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Identity\Application\DTO\IssueApiKeyInput;
use Src\Modules\Identity\Application\UseCase\IssueApiKey;
use Src\Modules\Identity\Application\UseCase\RevokeApiKey;
use Src\Modules\Identity\Domain\Repository\ApiKeyRepository;
use Src\Modules\Identity\Domain\ValueObject\AccountId;

/**
 * Painel do cliente: lista/cria/revoga API keys e mostra saldo de créditos.
 * O token recém-emitido vem em flash (one-shot) para a UI exibir uma única vez.
 */
final class ApiKeysController
{
    public function index(Request $request, ApiKeyRepository $apiKeys, WalletRepository $wallets): Response
    {
        $accountId = $request->user()->account_id;
        $wallet = $accountId !== null ? $wallets->findByAccountId($accountId) : null;

        $keys = $accountId !== null
            ? array_map(fn ($k) => [
                'id' => $k->id->value,
                'name' => $k->name,
                'prefix' => $k->prefix,
                'last_four' => $k->lastFour,
                'status' => $k->status->value,
                'last_used_at' => $k->lastUsedAt?->format(DATE_ATOM),
                'created_at' => $k->createdAt->format(DATE_ATOM),
            ], $apiKeys->listForAccount(new AccountId($accountId)))
            : [];

        return Inertia::render('client/api-keys/index', [
            'keys' => $keys,
            'wallet' => $wallet === null ? null : [
                'balance' => $wallet->balance()->value,
                'reserved' => $wallet->reserved()->value,
                'available' => $wallet->available()->value,
            ],
        ]);
    }

    public function store(Request $request, IssueApiKey $issue): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        abort_if($request->user()->account_id === null, 422, 'User has no account.');

        $issued = $issue->handle(new IssueApiKeyInput(
            accountId: $request->user()->account_id,
            name: $data['name'],
        ));

        // Token completo vai em flash one-shot: a UI mostra UMA vez e descarta.
        return back()->with([
            'success' => 'Chave criada — copie agora, não exibiremos novamente.',
            'plain_token' => $issued->plainToken,
        ]);
    }

    public function destroy(string $apiKeyId, Request $request, RevokeApiKey $revoke): RedirectResponse
    {
        abort_if($request->user()->account_id === null, 422);

        $revoke->handle($request->user()->account_id, $apiKeyId);

        return back()->with('success', 'Chave revogada.');
    }
}

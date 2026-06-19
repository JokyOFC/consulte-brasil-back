<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Modules\Identity\Application\DTO\UpdateAccountWebhookInput;
use Src\Modules\Identity\Application\UseCase\UpdateAccountWebhook;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;

#[Group('Webhook', description: 'Configuração do webhook de saída para consultas.', weight: 3)]
final class WebhookController
{
    #[Endpoint(
        title: 'Consultar webhook',
        description: 'Retorna a URL do webhook configurada para a conta. O secret nunca é exposto após a criação.',
    )]
    #[Response(
        status: 200,
        description: 'Configuração atual',
        examples: [[
            'data' => [
                'webhook_url' => 'https://example.com/webhooks/consulte',
                'webhook_configured' => true,
            ],
        ]],
    )]
    public function show(Request $request): JsonResponse
    {
        /** @var AccountModel $account */
        $account = $request->user();

        return response()->json([
            'data' => $this->toResponse($account),
        ]);
    }

    #[Endpoint(
        title: 'Atualizar webhook',
        description: 'Define ou remove a URL do webhook. Na primeira configuração (ou com `regenerate_secret: true`), um novo secret é gerado e retornado uma única vez na resposta.',
    )]
    #[BodyParameter('webhook_url', description: 'URL de destino. Envie `null` ou string vazia para remover.', required: false, type: 'string|null')]
    #[BodyParameter('regenerate_secret', description: 'Gera um novo secret HMAC.', required: false, type: 'boolean', example: false)]
    #[Response(status: 200, description: 'Webhook atualizado')]
    public function update(Request $request, UpdateAccountWebhook $update): JsonResponse
    {
        /** @var AccountModel $account */
        $account = $request->user();

        $data = $request->validate([
            'webhook_url' => ['nullable', 'string', 'max:2048'],
            'regenerate_secret' => ['sometimes', 'boolean'],
        ]);

        $output = $update->handle(new UpdateAccountWebhookInput(
            accountId: $account->id,
            webhookUrl: array_key_exists('webhook_url', $data) ? $data['webhook_url'] : $account->webhook_url,
            regenerateSecret: (bool) ($data['regenerate_secret'] ?? false),
        ));

        $response = $this->toResponse($account->fresh());

        if ($output->plainSecret !== null) {
            $response['webhook_secret'] = $output->plainSecret;
        }

        return response()->json(['data' => $response]);
    }

    /** @return array{webhook_url: string|null, webhook_configured: bool} */
    private function toResponse(AccountModel $account): array
    {
        return [
            'webhook_url' => $account->webhook_url,
            'webhook_configured' => $account->webhook_url !== null
                && $account->webhook_url !== ''
                && $account->webhook_secret !== null,
        ];
    }
}

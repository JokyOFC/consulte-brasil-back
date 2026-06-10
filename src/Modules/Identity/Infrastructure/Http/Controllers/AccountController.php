<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;

#[Group('Conta', description: 'Informações da conta autenticada.', weight: 1)]
final class AccountController
{
    #[Endpoint(
        title: 'Dados da conta',
        description: 'Retorna os dados da conta associada à chave de API usada na requisição. Útil para validar autenticação após gerar uma nova chave.',
    )]
    #[Response(
        status: 200,
        description: 'Conta autenticada',
        examples: [[
            'data' => [
                'id' => '01932a1b-8c4d-7000-8000-000000000001',
                'name' => 'Minha Empresa LTDA',
                'document' => '12345678000199',
                'status' => 'active',
                'authenticated_via_api_key' => '01932a1b-8c4d-7000-8000-000000000002',
            ],
        ]],
    )]
    #[Response(status: 401, description: 'Chave de API ausente, inválida ou revogada')]
    public function me(Request $request): JsonResponse
    {
        /** @var AccountModel $account */
        $account = $request->user();

        return response()->json([
            'data' => [
                'id' => $account->id,
                'name' => $account->name,
                'document' => $account->document,
                'status' => $account->status,
                'authenticated_via_api_key' => $request->attributes->get('consulte.api_key_id'),
            ],
        ]);
    }
}

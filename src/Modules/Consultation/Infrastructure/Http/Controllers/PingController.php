<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group('Sistema', description: 'Endpoints de saúde e metadados da API.', weight: 0)]
final class PingController
{
    /**
     * Verifica se a API está respondendo. Não requer autenticação.
     */
    #[Endpoint(
        title: 'Health check',
        description: 'Retorna status, nome do serviço e versão da API. Útil para monitoramento e testes de conectividade.',
    )]
    #[Response(
        status: 200,
        description: 'API operacional',
        examples: [[
            'status' => 'ok',
            'service' => 'consulte-brasil',
            'version' => 'v1',
        ]],
    )]
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'consulte-brasil',
            'version' => 'v1',
        ]);
    }
}

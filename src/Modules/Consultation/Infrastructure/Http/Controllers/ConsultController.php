<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Src\Modules\Consultation\Application\DTO\ExecuteConsultationInput;
use Src\Modules\Consultation\Application\UseCase\ExecuteConsultation;
use Src\Modules\Consultation\Infrastructure\Http\Requests\ExecuteConsultationRequest;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;

/**
 * Endpoint principal da API pública: executa consultas de dados.
 */
#[Group('Consultas', description: 'Execução de consultas de dados oficiais.', weight: 2)]
final class ConsultController
{
    #[Endpoint(
        title: 'Executar consulta',
        description: 'Executa uma consulta do tipo informado na URL. Veja a tabela de tipos disponíveis abaixo.',
    )]
    #[PathParameter(
        'queryType',
        description: 'Código do tipo de consulta. Ex.: cpf, cnpj, cpf_completo, cnpj_qsa, cnpj_razao, nfe.',
        required: true,
        example: 'cpf',
    )]
    #[BodyParameter(
        'params',
        description: 'Parâmetros da consulta. Para CPF/CNPJ, informe `document` com o número sem formatação.',
        required: true,
        type: 'object',
        example: ['document' => '11144477735'],
    )]
    #[Response(
        status: 200,
        description: 'Consulta realizada com sucesso',
        examples: [[
            'data' => [
                'consultation_id' => '01932a1b-8c4d-7000-8000-000000000001',
                'amount_charged' => 29,
                'amount_charged_formatted' => 'R$ 0,29',
                'credits_charged' => 29,
                'from_cache' => false,
                'data' => [
                    'name' => 'JOAO DA SILVA',
                    'birth_date' => '1990-01-15',
                    'mother_name' => 'MARIA DA SILVA',
                    'gender' => 'M',
                    'raw' => [],
                ],
            ],
        ]],
    )]
    #[Response(status: 402, description: 'Saldo insuficiente na carteira')]
    #[Response(status: 422, description: 'Tipo de consulta inválido ou parâmetros incorretos')]
    #[Response(status: 503, description: 'Nenhum provedor disponível para o tipo solicitado')]
    public function __invoke(
        string $queryType,
        ExecuteConsultationRequest $request,
        ExecuteConsultation $execute,
    ): JsonResponse {
        /** @var AccountModel $account */
        $account = $request->user();
        $apiKeyId = $request->attributes->get('consulte.api_key_id');

        $output = $execute->handle(new ExecuteConsultationInput(
            accountId: $account->id,
            apiKeyId: is_string($apiKeyId) ? $apiKeyId : null,
            queryType: $queryType,
            params: (array) $request->input('params'),
        ));

        return response()->json([
            'data' => [
                'consultation_id' => $output->consultationId,
                'provider' => $output->providerIdentifier,
                // Valor cobrado em centavos de BRL (e versão formatada).
                'amount_charged' => $output->creditsCharged,
                'amount_charged_formatted' => 'R$ '.number_format($output->creditsCharged / 100, 2, ',', '.'),
                // Mantido por compatibilidade: mesmo valor em centavos.
                'credits_charged' => $output->creditsCharged,
                'from_cache' => $output->fromCache,
                'data' => $output->data,
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Src\Modules\Consultation\Application\Service\ClientConsultationCatalog;

#[Group('Serviços', description: 'Catálogo público de consultas disponíveis.', weight: 2)]
final class ServicesController
{
    #[Endpoint(
        title: 'Listar serviços',
        description: 'Retorna os tipos de consulta ativos e habilitados. Não requer autenticação. Valores em centavos de BRL.',
    )]
    #[Response(
        status: 200,
        description: 'Lista de serviços disponíveis',
        examples: [[
            'data' => [[
                'code' => 'cpf',
                'name' => 'Dados cadastrais de PF',
                'description' => 'Consulta básica de CPF',
                'group' => 'Pessoa Física (CPF)',
                'price_cents' => 29,
                'price_formatted' => 'R$ 0,29',
            ]],
            'groups' => [[
                'name' => 'Pessoa Física (CPF)',
                'services' => [[
                    'code' => 'cpf',
                    'name' => 'Dados cadastrais de PF',
                    'description' => 'Consulta básica de CPF',
                    'group' => 'Pessoa Física (CPF)',
                    'price_cents' => 29,
                    'price_formatted' => 'R$ 0,29',
                ]],
            ]],
        ]],
    )]
    public function index(ClientConsultationCatalog $catalog): JsonResponse
    {
        $services = array_map(
            fn (array $type) => $this->toPublicArray($type),
            $catalog->list(),
        );

        $groups = [];

        foreach (ClientConsultationCatalog::GROUP_ORDER as $groupName) {
            $items = array_values(array_filter(
                $services,
                fn (array $service) => $service['group'] === $groupName,
            ));

            if ($items === []) {
                continue;
            }

            $groups[] = [
                'name' => $groupName,
                'services' => $items,
            ];
        }

        return response()->json([
            'data' => $services,
            'groups' => $groups,
        ]);
    }

    #[Endpoint(
        title: 'Detalhe do serviço',
        description: 'Retorna um tipo de consulta ativo pelo código (ex.: `cpf`). Não requer autenticação.',
    )]
    #[Response(status: 404, description: 'Serviço inexistente ou indisponível')]
    public function show(string $code, ClientConsultationCatalog $catalog): JsonResponse
    {
        foreach ($catalog->list() as $type) {
            if ($type['code'] !== $code) {
                continue;
            }

            return response()->json([
                'data' => $this->toPublicArray($type),
            ]);
        }

        return response()->json([
            'error' => [
                'type' => 'service_not_found',
                'message' => 'Serviço não encontrado.',
            ],
        ], 404);
    }

    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     description: string|null,
     *     group: string,
     *     price_cents: int,
     *     param_field?: string,
     *     param_label?: string,
     *     param_placeholder?: string,
     * }  $type
     * @return array<string, mixed>
     */
    private function toPublicArray(array $type): array
    {
        return [
            'code' => $type['code'],
            'name' => $type['name'],
            'description' => $type['description'],
            'group' => $type['group'],
            'price_cents' => $type['price_cents'],
            'price_formatted' => $this->brl($type['price_cents']),
        ];
    }

    private function brl(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}

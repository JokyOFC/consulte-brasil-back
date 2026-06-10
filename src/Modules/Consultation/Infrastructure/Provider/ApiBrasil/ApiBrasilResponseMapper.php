<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Provider\ApiBrasil;

use Src\Modules\Consultation\Domain\ValueObject\ConsultationResult;
use Src\Modules\Consultation\Domain\ValueObject\ProviderMetadata;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;

/**
 * Traduz o payload bruto da API Brasil para o ConsultationResult
 * NORMALIZADO. Mantém o Core isolado de qualquer mudança no JSON do
 * fornecedor — quando a API Brasil mudar o formato, só esta classe muda.
 *
 * O mapa de campos é direcionado por QueryType. Ajuste conforme a doc
 * oficial: chave do array externo = nome normalizado interno;
 * valor = caminho com pontos no payload da API Brasil.
 */
final class ApiBrasilResponseMapper
{
    /** @var array<string, array<string, string>> */
    private const FIELD_MAP = [
        'cpf' => [
            'name' => 'response.nome',
            'birth_date' => 'response.data_nascimento',
            'status' => 'response.situacao',
            'mother_name' => 'response.nome_mae',
        ],
        'cnpj' => [
            'corporate_name' => 'response.razao_social',
            'trade_name' => 'response.nome_fantasia',
            'status' => 'response.situacao',
            'opened_at' => 'response.data_abertura',
        ],
    ];

    /**
     * Detecta o envelope de erro da APIBrasil em respostas 2xx.
     * Convenção do gateway: `{"error": true, "message": "..."}`.
     *
     * @param  array<string, mixed>  $body
     */
    public function isError(array $body): bool
    {
        if (($body['error'] ?? false) === true) {
            return true;
        }

        // Sem corpo útil também é tratado como falha (nada para entregar).
        return $body === [];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $extraMeta
     */
    public function toResult(QueryType $type, array $body, array $extraMeta = []): ConsultationResult
    {
        $map = self::FIELD_MAP[$type->code] ?? [];

        if ($map === []) {
            // Sem mapa específico: passthrough do payload útil. A APIBrasil
            // devolve o dado sob "response"/"data"; caímos no corpo inteiro
            // como fallback. O bruto fica sempre sob "raw".
            $payload = $body['response'] ?? $body['data'] ?? $body;
            $data = is_array($payload) ? $payload : ['result' => $payload];
            $data['raw'] = $body;

            return $this->buildResult($data, $extraMeta);
        }

        $data = [];

        foreach ($map as $normalized => $path) {
            $value = $this->dig($body, $path);
            if ($value !== null) {
                $data[$normalized] = $value;
            }
        }

        // Mantém o payload bruto sob "raw" para clientes que precisem.
        $data['raw'] = $body;

        return $this->buildResult($data, $extraMeta);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $extraMeta
     */
    private function buildResult(array $data, array $extraMeta): ConsultationResult
    {
        return new ConsultationResult(
            data: $data,
            meta: new ProviderMetadata(
                providerIdentifier: ApiBrasilHttpClient::PROVIDER_IDENTIFIER,
                latencyMs: $extraMeta['latency_ms'] ?? null,
                httpStatus: $extraMeta['http_status'] ?? null,
            ),
        );
    }

    /** Acessa "a.b.c" em um array aninhado. */
    private function dig(mixed $payload, string $path): mixed
    {
        foreach (explode('.', $path) as $segment) {
            if (! is_array($payload) || ! array_key_exists($segment, $payload)) {
                return null;
            }
            $payload = $payload[$segment];
        }

        return $payload;
    }
}

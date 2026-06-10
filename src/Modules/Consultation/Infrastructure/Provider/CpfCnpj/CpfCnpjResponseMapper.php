<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj;

use Illuminate\Support\Arr;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationResult;
use Src\Modules\Consultation\Domain\ValueObject\ProviderMetadata;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;

/**
 * Traduz o payload bruto da API CPF.CNPJ para o ConsultationResult
 * NORMALIZADO, mantendo o Core isolado do formato do fornecedor.
 *
 * Sucesso é sinalizado por "status": 1. Falhas trazem "erro"/"erroCodigo".
 */
final class CpfCnpjResponseMapper
{
    /** @var array<string, array<string, string>> */
    private const FIELD_MAP = [
        'cpf' => [
            'name' => 'nome',
            'birth_date' => 'nascimento',
            'status' => 'situacao',
            'mother_name' => 'mae',
        ],
        'cnpj' => [
            'corporate_name' => 'razao',
            'trade_name' => 'fantasia',
            'status' => 'situacao.nome',
            'opened_at' => 'inicioAtividade',
        ],
    ];

    /**
     * Detecta falha de negócio retornada em respostas 2xx:
     *  - corpo vazio;
     *  - presença de "erro"/"erroCodigo";
     *  - "status" diferente de 1 (sucesso).
     *
     * @param  array<string, mixed>  $body
     */
    public function isError(array $body): bool
    {
        if ($body === []) {
            return true;
        }

        if (array_key_exists('erro', $body) || array_key_exists('erroCodigo', $body)) {
            return true;
        }

        if (array_key_exists('status', $body)) {
            return (int) $body['status'] !== 1;
        }

        return false;
    }

    /** Campos de controle/meta do provedor que não fazem parte do dado em si. */
    private const META_KEYS = ['status', 'pacoteUsado', 'saldo', 'consultaID', 'delay'];

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $extraMeta
     */
    public function toResult(QueryType $type, array $body, array $extraMeta = []): ConsultationResult
    {
        // Remove metadados de controle/billing do provedor (saldo, pacoteUsado,
        // consultaID, delay, status) para que não vazem em nenhum lugar.
        $clean = Arr::except($body, self::META_KEYS);

        $map = self::FIELD_MAP[$type->code] ?? null;

        if ($map !== null) {
            // Tipos com mapa dedicado (cpf/cnpj): expõe campos normalizados.
            $data = [];
            foreach ($map as $normalized => $path) {
                $value = $this->dig($clean, $path);
                if ($value !== null) {
                    $data[$normalized] = $value;
                }
            }
        } else {
            // Demais pacotes: passthrough de todos os campos do provedor.
            $data = $clean;
        }

        $data['raw'] = $clean;

        return new ConsultationResult(
            data: $data,
            meta: new ProviderMetadata(
                providerIdentifier: CpfCnpjHttpClient::PROVIDER_IDENTIFIER,
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

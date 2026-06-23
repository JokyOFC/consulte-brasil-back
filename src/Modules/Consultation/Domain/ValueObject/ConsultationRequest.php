<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Domain\ValueObject;

/**
 * Solicitação normalizada de consulta, agnóstica de provedor.
 *
 * O documento (CPF/CNPJ/CEP/placa) é higienizado na entrada — só alfanumérico
 * em maiúsculas — para que QUALQUER formatação ("035.521.340-00", "03552134000")
 * gere o MESMO fingerprint. Sem isso, formatos diferentes do mesmo documento
 * viram chaves de cache distintas e o cliente é cobrado em duplicidade.
 */
final readonly class ConsultationRequest
{
    /** @var array<string, mixed> */
    public array $params;

    /** @param array<string, mixed> $params */
    public function __construct(
        public QueryType $type,
        array $params,
    ) {
        $this->params = self::normalizeParams($params);
    }

    public function fingerprint(): string
    {
        $params = $this->params;
        ksort($params);

        return hash('sha256', $this->type->code.'|'.json_encode($params, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private static function normalizeParams(array $params): array
    {
        if (isset($params['document']) && is_string($params['document'])) {
            $clean = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $params['document']) ?? '');

            if ($clean !== '') {
                $params['document'] = $clean;
            }
        }

        return $params;
    }
}

<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Application\Service;

/**
 * Normaliza parâmetros de consulta para que painel web, API pública e cache
 * usem exatamente a mesma chave (fingerprint) e o mesmo payload ao provedor.
 */
final class ConsultationParamsNormalizer
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function normalize(array $params): array
    {
        if (! array_key_exists('document', $params)) {
            return $params;
        }

        $document = (string) $params['document'];
        $clean = preg_replace('/[^0-9A-Za-z]/', '', $document) ?? '';

        if ($clean !== '') {
            $params['document'] = strtoupper($clean);
        }

        return $params;
    }
}

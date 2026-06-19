<?php

declare(strict_types=1);

namespace Src\Modules\Audit\Infrastructure\Http\Support;

use Illuminate\Support\Str;

/**
 * Reduz respostas da API antes de persistir em request_logs.
 * Remove apenas campos pesados (PDF/base64); nunca altera a resposta HTTP ao cliente.
 */
final class RequestResponseSummarizer
{
    /** Limite conservador antes da cifra Laravel (coluna LONGTEXT, margem de segurança). */
    private const MAX_JSON_BYTES = 120_000;

    /** @var list<string> */
    private const HEAVY_KEYS = [
        'raw',
        'comprovantePdfBase64',
        'pdfBase64',
        'certificadoPdfBase64',
        'attachments',
    ];

    public function summarize(string $content): array
    {
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return ['raw' => Str::limit($content, 2000)];
        }

        /** @var array<string, mixed> $clone */
        $clone = json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        return $this->enforceMaxSize($this->stripHeavyFields($clone));
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array<string, mixed>
     */
    private function stripHeavyFields(array $decoded): array
    {
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $decoded['data'] = $this->stripHeavyFieldsRecursive($decoded['data']);
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function stripHeavyFieldsRecursive(array $data): array
    {
        foreach (self::HEAVY_KEYS as $key) {
            unset($data[$key]);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $data[$key] = $this->stripHeavyFieldsRecursive($value);
            } elseif (is_string($value) && strlen($value) > 4_000) {
                $data[$key] = Str::limit($value, 500, '…');
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function enforceMaxSize(array $data): array
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);

        if (strlen($json) <= self::MAX_JSON_BYTES) {
            return $data;
        }

        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = $this->aggressivelyTrim($data['data']);
            $json = json_encode($data, JSON_THROW_ON_ERROR);

            if (strlen($json) <= self::MAX_JSON_BYTES) {
                return $data;
            }
        }

        $consultationId = null;
        if (isset($data['data']) && is_array($data['data'])) {
            $candidate = $data['data']['consultation_id'] ?? null;
            $consultationId = is_string($candidate) ? $candidate : null;
        }

        return array_filter([
            'data' => array_filter([
                'consultation_id' => $consultationId,
                'provider' => is_array($data['data'] ?? null) ? ($data['data']['provider'] ?? null) : null,
                'amount_charged' => is_array($data['data'] ?? null) ? ($data['data']['amount_charged'] ?? null) : null,
                'from_cache' => is_array($data['data'] ?? null) ? ($data['data']['from_cache'] ?? null) : null,
                '_audit_truncated' => true,
            ]),
            'error' => is_array($data['error'] ?? null) ? $data['error'] : null,
        ], static fn ($value) => $value !== null);
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function aggressivelyTrim(array $block): array
    {
        unset($block['data']);

        foreach ($block as $key => $value) {
            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $block[$key] = $this->stripHeavyFieldsRecursive($value);
            } elseif (is_string($value) && strlen($value) > 500) {
                $block[$key] = Str::limit($value, 500, '…');
            }
        }

        return $block;
    }
}

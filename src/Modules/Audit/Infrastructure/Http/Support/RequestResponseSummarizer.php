<?php

declare(strict_types=1);

namespace Src\Modules\Audit\Infrastructure\Http\Support;

use Illuminate\Support\Str;

/**
 * Reduz respostas da API antes de persistir em request_logs.
 * Consultas podem retornar PDFs/base64 enormes — o log guarda só metadados.
 */
final class RequestResponseSummarizer
{
    /** Limite conservador antes da cifra Laravel (coluna TEXT ≈ 64 KiB). */
    private const MAX_JSON_BYTES = 48_000;

    /** @var list<string> */
    private const OMITTED_RESPONSE_DATA_KEYS = [
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

        return $this->enforceMaxSize($this->trimHeavyFields($decoded));
    }

    /** @param array<string, mixed> $decoded */
    private function trimHeavyFields(array $decoded): array
    {
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $decoded['data'] = $this->summarizeApiDataBlock($decoded['data']);
        }

        return $decoded;
    }

    /**
     * Envelope de sucesso da API: { consultation_id, provider, data: {...} }.
     *
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function summarizeApiDataBlock(array $block): array
    {
        if (! isset($block['data']) || ! is_array($block['data'])) {
            return $block;
        }

        $payload = $block['data'];
        $block['data'] = [
            '_omitted' => true,
            'field_count' => count($payload),
            'fields' => array_values(array_slice(array_keys($payload), 0, 50)),
        ];

        foreach (self::OMITTED_RESPONSE_DATA_KEYS as $key) {
            unset($block[$key]);
        }

        return $block;
    }

    /** @param array<string, mixed> $data */
    private function enforceMaxSize(array $data): array
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);

        if (strlen($json) <= self::MAX_JSON_BYTES) {
            return $data;
        }

        $consultationId = null;
        if (isset($data['data']) && is_array($data['data'])) {
            $candidate = $data['data']['consultation_id'] ?? null;
            $consultationId = is_string($candidate) ? $candidate : null;
        }

        return array_filter([
            'data' => array_filter([
                'consultation_id' => $consultationId,
                '_truncated' => true,
            ]),
            'error' => is_array($data['error'] ?? null) ? $data['error'] : null,
        ], static fn ($value) => $value !== null);
    }
}

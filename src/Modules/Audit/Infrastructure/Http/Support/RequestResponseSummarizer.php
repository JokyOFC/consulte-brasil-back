<?php

declare(strict_types=1);

namespace Src\Modules\Audit\Infrastructure\Http\Support;

use Illuminate\Support\Str;

/**
 * Prepara respostas da API para persistir em request_logs.
 *
 * Remove somente conteúdo binário/pesado (PDF base64, raw do provedor).
 * Campos de negócio (nome, CPF, status, endereço, erros…) são preservados.
 */
final class RequestResponseSummarizer
{
    /** @var list<string> */
    private const HEAVY_KEYS = [
        'raw',
        'comprovantePdfBase64',
        'pdfBase64',
        'certificadoPdfBase64',
        'attachments',
    ];

    /** Strings acima disso são resumidas (evita HTML/texto gigante do provedor). */
    private const MAX_FIELD_CHARS = 8_000;

    public function summarize(string $content): array
    {
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return ['raw' => Str::limit($content, 2_000)];
        }

        /** @var array<string, mixed> $clone */
        $clone = json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        $omitted = [];
        $stripped = $this->stripHeavyFields($clone, $omitted);

        if ($omitted !== []) {
            $stripped['_audit_omitted'] = array_values(array_unique($omitted));
        }

        return $this->truncateLongStrings($stripped);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  list<string>  $omitted
     * @return array<string, mixed>
     */
    private function stripHeavyFields(array $decoded, array &$omitted): array
    {
        foreach ($decoded as $key => $value) {
            if (is_string($key) && $this->isHeavyKey($key)) {
                unset($decoded[$key]);
                $omitted[] = $key;

                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $decoded[$key] = $this->stripHeavyFields($value, $omitted);
            }
        }

        return $decoded;
    }

    private function isHeavyKey(string $key): bool
    {
        if (in_array($key, self::HEAVY_KEYS, true)) {
            return true;
        }

        $lower = strtolower($key);

        return str_ends_with($lower, 'base64')
            || str_contains($lower, 'pdfbase64')
            || $lower === 'pdf';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function truncateLongStrings(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($key === '_audit_omitted') {
                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $data[$key] = $this->truncateLongStrings($value);
            } elseif (is_string($value) && strlen($value) > self::MAX_FIELD_CHARS) {
                $data[$key] = Str::limit($value, self::MAX_FIELD_CHARS, '…');
            }
        }

        return $data;
    }
}

<?php

declare(strict_types=1);

namespace Src\Modules\Audit\Infrastructure\Http\Support;

use Illuminate\Support\Str;

/**
 * Ajusta arrays cifrados (encrypted:array) para caber na coluna do banco.
 *
 * Com colunas LONGTEXT (migration 2026_06_19_000002), o limite é alto o suficiente
 * para guardar o resultado completo da consulta — exceto PDFs/base64, removidos
 * antes pelo RequestResponseSummarizer.
 *
 * Só encurta strings individuais muito longas; nunca descarta o payload inteiro.
 */
final class AuditEncryptedPayloadLimiter
{
    /**
     * JSON plaintext antes da cifra Laravel (~3× → cabe folgado em LONGTEXT).
     * Requer migration widen_request_logs_encrypted_columns.
     */
    public const MAX_JSON_BYTES = 400_000;

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    public function fit(?array $data, int $maxBytes = self::MAX_JSON_BYTES): ?array
    {
        if ($data === null || $data === []) {
            return $data;
        }

        foreach ([8_000, 2_000, 500] as $maxStringLength) {
            $trimmed = $this->truncateStrings($data, $maxStringLength);
            if (strlen(json_encode($trimmed, JSON_THROW_ON_ERROR)) <= $maxBytes) {
                return $trimmed;
            }
        }

        return $this->truncateStrings($data, 200);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function truncateStrings(array $data, int $maxStringLength): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $data[$key] = $this->truncateStrings($value, $maxStringLength);
            } elseif (is_string($value) && strlen($value) > $maxStringLength) {
                $data[$key] = Str::limit($value, $maxStringLength, '…');
            }
        }

        return $data;
    }
}

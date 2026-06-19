<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Webhook;

final class WebhookSignatureGenerator
{
    public const HEADER_NAME = 'X-Consulte-Signature';

    public const MAX_AGE_SECONDS = 300;

    /**
     * @return array{header: string, timestamp: int}
     */
    public function generate(string $payload, string $secret, ?int $timestamp = null): array
    {
        $timestamp ??= time();
        $signedContent = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedContent, $secret);

        return [
            'header' => sprintf('t=%d,v1=%s', $timestamp, $signature),
            'timestamp' => $timestamp,
        ];
    }

    public function verify(string $payload, string $secret, string $header, ?int $now = null): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $piece) {
            $kv = explode('=', trim($piece), 2);
            if (count($kv) === 2) {
                $parts[trim($kv[0])] = trim($kv[1]);
            }
        }

        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? null;

        if ($timestamp === null || $signature === null || ! is_numeric($timestamp)) {
            return false;
        }

        $now ??= time();
        if (abs($now - (int) $timestamp) > self::MAX_AGE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return hash_equals($expected, $signature);
    }
}

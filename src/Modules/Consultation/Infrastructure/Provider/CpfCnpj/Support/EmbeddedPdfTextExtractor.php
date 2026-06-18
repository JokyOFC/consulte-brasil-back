<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support;

use Smalot\PdfParser\Parser;

class EmbeddedPdfTextExtractor
{
    public function extract(string $base64): ?string
    {
        $binary = base64_decode($this->normalizePayload($base64), true);

        if ($binary === false || $binary === '') {
            return null;
        }

        try {
            $pdf = (new Parser)->parseContent($binary);
            $text = trim(preg_replace('/\s+/u', ' ', $pdf->getText()) ?? '');

            return $text !== '' ? $text : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizePayload(string $base64): string
    {
        $payload = trim($base64);

        if (str_contains($payload, ',')) {
            $payload = (string) substr($payload, (int) strrpos($payload, ',') + 1);
        }

        return preg_replace('/\s+/', '', $payload) ?? $payload;
    }
}

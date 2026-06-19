<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support;

use Smalot\PdfParser\Parser;

class EmbeddedPdfTextExtractor
{
    private ?PdfOcr $ocr;

    public function __construct(?PdfOcr $ocr = null)
    {
        $this->ocr = $ocr;
    }

    public function extract(string $base64): ?string
    {
        $binary = base64_decode($this->normalizePayload($base64), true);

        if ($binary === false || $binary === '') {
            return null;
        }

        $text = $this->extractEmbeddedText($binary);

        if ($text !== null) {
            return $text;
        }

        // PDF sem camada de texto (imagem/escaneado) → tenta OCR como fallback.
        return $this->ocr()->extractText($binary);
    }

    private function extractEmbeddedText(string $binary): ?string
    {
        try {
            $pdf = (new Parser)->parseContent($binary);
            $text = trim(preg_replace('/\s+/u', ' ', $pdf->getText()) ?? '');

            return $text !== '' ? $text : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function ocr(): PdfOcr
    {
        return $this->ocr ??= new TesseractPdfOcr;
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

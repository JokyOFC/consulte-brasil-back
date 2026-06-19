<?php

declare(strict_types=1);

namespace Tests\Unit\Consultation;

use PHPUnit\Framework\TestCase;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\EmbeddedPdfTextExtractor;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\PdfOcr;

final class EmbeddedPdfTextExtractorOcrFallbackTest extends TestCase
{
    public function test_falls_back_to_ocr_when_pdf_has_no_text_layer(): void
    {
        $ocr = new class implements PdfOcr
        {
            public function extractText(string $pdfBinary): ?string
            {
                return 'NÃO CONSTA condenação em nome de FULANO, CPF 035.521.340-00.';
            }
        };

        $extractor = new EmbeddedPdfTextExtractor($ocr);

        // Bytes que não são um PDF parseável → Smalot falha → cai no OCR.
        $text = $extractor->extract(base64_encode('isto-nao-e-um-pdf-com-texto'));

        $this->assertNotNull($text);
        $this->assertStringContainsString('NÃO CONSTA', $text);
    }

    public function test_returns_null_when_no_text_and_ocr_yields_nothing(): void
    {
        $ocr = new class implements PdfOcr
        {
            public function extractText(string $pdfBinary): ?string
            {
                return null;
            }
        };

        $extractor = new EmbeddedPdfTextExtractor($ocr);

        $this->assertNull($extractor->extract(base64_encode('isto-nao-e-um-pdf-com-texto')));
    }

    public function test_invalid_base64_never_reaches_ocr(): void
    {
        $ocr = new class implements PdfOcr
        {
            public bool $called = false;

            public function extractText(string $pdfBinary): ?string
            {
                $this->called = true;

                return 'OCR';
            }
        };

        $extractor = new EmbeddedPdfTextExtractor($ocr);

        $this->assertNull($extractor->extract(''));
        $this->assertFalse($ocr->called);
    }
}

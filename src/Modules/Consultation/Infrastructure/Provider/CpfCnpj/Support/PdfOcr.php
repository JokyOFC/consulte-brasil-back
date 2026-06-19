<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support;

/**
 * Extrai texto de um PDF sem camada de texto (imagem/escaneado) via OCR.
 */
interface PdfOcr
{
    /**
     * Texto reconhecido no PDF, ou null se indisponível/sem resultado.
     */
    public function extractText(string $pdfBinary): ?string;
}

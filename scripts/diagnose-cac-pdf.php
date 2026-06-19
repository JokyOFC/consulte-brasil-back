<?php

declare(strict_types=1);

/**
 * Diagnóstico de extração de texto do PDF do CAC (cpf_cac).
 *
 * Uso:
 *   php scripts/diagnose-cac-pdf.php "C:\caminho\para\certificado.pdf"
 *
 * Reporta: tamanho do binário, nº de páginas, comprimento do texto extraído
 * pelo Smalot, amostra do texto e se aparenta ser PDF imagem (sem camada de
 * texto → precisa de OCR).
 */

require __DIR__.'/../vendor/autoload.php';

use Smalot\PdfParser\Parser;

$path = $argv[1] ?? null;

if ($path === null || ! is_file($path)) {
    fwrite(STDERR, "Informe um caminho válido para o PDF.\nEx.: php scripts/diagnose-cac-pdf.php \"C:\\Users\\voce\\Downloads\\cac.pdf\"\n");
    exit(1);
}

$binary = (string) file_get_contents($path);
printf("Arquivo: %s\n", $path);
printf("Tamanho binário: %d bytes\n", strlen($binary));
printf("Começa com %%PDF: %s\n", str_starts_with($binary, '%PDF') ? 'sim' : 'NÃO (pode não ser PDF)');

try {
    $pdf = (new Parser)->parseContent($binary);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Smalot falhou ao parsear: '.$e->getMessage()."\n");
    exit(2);
}

$pages = $pdf->getPages();
printf("Páginas: %d\n", count($pages));

$fullText = trim(preg_replace('/\s+/u', ' ', $pdf->getText()) ?? '');
printf("Comprimento do texto extraído (total): %d chars\n", mb_strlen($fullText));

foreach ($pages as $i => $page) {
    $t = trim(preg_replace('/\s+/u', ' ', $page->getText()) ?? '');
    printf("  - Página %d: %d chars de texto\n", $i + 1, mb_strlen($t));
}

echo "\n=== Amostra do texto (até 1200 chars) ===\n";
echo mb_substr($fullText, 0, 1200);
echo "\n=== fim da amostra ===\n\n";

if (mb_strlen($fullText) < 30) {
    echo "VEREDITO: praticamente NENHUM texto extraído.\n";
    echo "→ PDF provavelmente é imagem/escaneado (sem camada de texto). Vai precisar de OCR.\n";
} else {
    echo "VEREDITO: o PDF TEM camada de texto extraível pelo Smalot.\n";
    echo "→ O problema NÃO é o extractor. Provável: cache antigo, ou o parser de campos não casou os regex.\n";
}

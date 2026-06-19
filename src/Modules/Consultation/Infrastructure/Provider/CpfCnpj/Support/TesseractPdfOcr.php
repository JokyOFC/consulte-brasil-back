<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * OCR de fallback para PDFs sem camada de texto (ex.: certidões escaneadas).
 *
 * Pipeline 100% local: Ghostscript rasteriza as páginas em PNG e o Tesseract
 * extrai o texto. Nenhum dado sensível (antecedentes criminais) sai do servidor.
 *
 * Degrada com elegância: se os binários não existirem ou qualquer etapa falhar,
 * devolve null — o enriquecimento simplesmente não acontece e a consulta nunca
 * quebra por causa do OCR.
 *
 * Requisitos no servidor:
 *   - ghostscript  (binário `gs`, ou `gswin64c`/`gswin32c` no Windows)
 *   - tesseract-ocr com o idioma português (`tesseract`, traineddata `por`)
 */
final class TesseractPdfOcr implements PdfOcr
{
    public function extractText(string $pdfBinary): ?string
    {
        if (! $this->enabled() || $pdfBinary === '') {
            return null;
        }

        $ghostscript = $this->ghostscriptBinary();
        $tesseract = $this->tesseractBinary();

        if ($ghostscript === null || $tesseract === null) {
            $this->warnMissingBinaries($ghostscript, $tesseract);

            return null;
        }

        $workdir = $this->makeWorkdir();
        if ($workdir === null) {
            return null;
        }

        try {
            $pdfPath = $workdir.DIRECTORY_SEPARATOR.'input.pdf';
            if (file_put_contents($pdfPath, $pdfBinary) === false) {
                return null;
            }

            $images = $this->rasterize($ghostscript, $pdfPath, $workdir);
            if ($images === []) {
                return null;
            }

            $text = '';
            foreach ($images as $image) {
                $text .= ' '.$this->ocrImage($tesseract, $image);
            }

            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            $this->warn('audit.ocr_failed', $e->getMessage());

            return null;
        } finally {
            $this->cleanup($workdir);
        }
    }

    /**
     * Rasteriza as primeiras páginas do PDF em PNGs (uma por página).
     *
     * @return list<string> caminhos das imagens geradas, em ordem
     */
    private function rasterize(string $ghostscript, string $pdfPath, string $workdir): array
    {
        $pattern = $workdir.DIRECTORY_SEPARATOR.'page-%03d.png';

        $process = new Process([
            $ghostscript,
            '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER',
            '-sDEVICE=png16m',
            '-r'.$this->dpi(),
            '-dFirstPage=1',
            '-dLastPage='.$this->maxPages(),
            '-sOutputFile='.$pattern,
            $pdfPath,
        ]);
        $process->setTimeout((float) $this->timeout());
        $process->run();

        if (! $process->isSuccessful()) {
            $this->warn('audit.ocr_rasterize_failed', $process->getErrorOutput());

            return [];
        }

        $images = glob($workdir.DIRECTORY_SEPARATOR.'page-*.png') ?: [];
        sort($images);

        return array_values($images);
    }

    private function ocrImage(string $tesseract, string $image): string
    {
        $process = new Process([
            $tesseract,
            $image,
            'stdout',
            '-l', $this->language(),
            '--psm', '6',
            '--dpi', (string) $this->dpi(),
        ]);
        $process->setTimeout((float) $this->timeout());
        $process->run();

        return $process->isSuccessful() ? $process->getOutput() : '';
    }

    private function ghostscriptBinary(): ?string
    {
        $configured = $this->config('ghostscript_bin', null);
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $finder = new ExecutableFinder;
        foreach (['gs', 'gswin64c', 'gswin32c'] as $name) {
            $path = $finder->find($name);
            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    private function tesseractBinary(): ?string
    {
        $configured = $this->config('tesseract_bin', null);
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return (new ExecutableFinder)->find('tesseract');
    }

    private function makeWorkdir(): ?string
    {
        try {
            $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'consulte-ocr-'.bin2hex(random_bytes(6));
        } catch (\Throwable) {
            return null;
        }

        return (@mkdir($dir, 0700, true) || is_dir($dir)) ? $dir : null;
    }

    private function cleanup(?string $dir): void
    {
        if ($dir === null || ! is_dir($dir)) {
            return;
        }

        foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($dir);
    }

    private function enabled(): bool
    {
        return (bool) $this->config('enabled', true);
    }

    private function language(): string
    {
        $lang = $this->config('language', 'por');

        return is_string($lang) && $lang !== '' ? $lang : 'por';
    }

    private function dpi(): int
    {
        return max(72, (int) $this->config('dpi', 300));
    }

    private function maxPages(): int
    {
        return max(1, (int) $this->config('max_pages', 3));
    }

    private function timeout(): int
    {
        return max(5, (int) $this->config('timeout_seconds', 60));
    }

    /**
     * Lê config/consultation.php de forma tolerante: funciona mesmo sem um app
     * Laravel inicializado (testes unitários puros) caindo para o default.
     */
    private function config(string $key, mixed $default): mixed
    {
        if (! function_exists('config')) {
            return $default;
        }

        try {
            $value = config('consultation.ocr.'.$key, $default);
        } catch (\Throwable) {
            return $default;
        }

        return $value ?? $default;
    }

    private function warnMissingBinaries(?string $ghostscript, ?string $tesseract): void
    {
        $missing = [];
        if ($ghostscript === null) {
            $missing[] = 'ghostscript (gs/gswin64c)';
        }
        if ($tesseract === null) {
            $missing[] = 'tesseract';
        }

        $this->warn('audit.ocr_unavailable', 'binários ausentes: '.implode(', ', $missing));
    }

    private function warn(string $event, string $message): void
    {
        if (! function_exists('logger')) {
            return;
        }

        try {
            logger()->warning($event, ['error' => $message]);
        } catch (\Throwable) {
            // Logar nunca pode quebrar o OCR.
        }
    }
}

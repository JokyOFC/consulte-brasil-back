<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support;

final class EmbeddedPdfEnricher
{
    /** @var list<string> */
    private const PDF_FIELD_KEYS = [
        'comprovantePdfBase64',
        'pdfBase64',
        'certificadoPdfBase64',
    ];

    public function __construct(
        private EmbeddedPdfTextExtractor $extractor,
        private CertificateTextParser $parser,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function enrich(array $data): array
    {
        if (isset($data['raw']) && is_array($data['raw'])) {
            /** @var array<string, mixed> $raw */
            $raw = $data['raw'];
            unset($data['raw']);
            $data = $this->applyRootIdentityFallback($this->walk($data));
            $data['raw'] = $raw;

            return $data;
        }

        $data = $this->walk($data);

        return $this->applyRootIdentityFallback($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function containsPendingPdf(array $data): bool
    {
        return $this->containsPendingPdfNode($data) || $this->containsStaleCertificateNode($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function needsRefresh(array $data, bool $force = false): bool
    {
        if ($force) {
            return true;
        }

        return $this->containsPendingPdf($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyRootIdentityFallback(array $data): array
    {
        $rootNome = $this->resolveRootNome($data);
        $rootCpf = is_string($data['cpf'] ?? null) ? $data['cpf'] : null;

        if (is_array($data['cac'] ?? null)) {
            /** @var array<string, mixed> $cac */
            $cac = $data['cac'];
            $data['cac'] = $this->mergeRootIdentityIntoCertificate($cac, $rootNome, $rootCpf);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveRootNome(array $data): ?string
    {
        foreach ([$data['nome'] ?? null, is_array($data['cac'] ?? null) ? ($data['cac']['nome'] ?? null) : null] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '' && ! $this->looksLikeInvalidCertificateName($candidate)) {
                return trim($candidate);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function mergeRootIdentityIntoCertificate(array $node, ?string $rootNome, ?string $rootCpf): array
    {
        if (! is_array($node['certificado'] ?? null)) {
            return $node;
        }

        /** @var array<string, mixed> $certificado */
        $certificado = $node['certificado'];

        if ($rootNome !== null) {
            $certificado['nome'] = $rootNome;
        } elseif (is_string($certificado['nome'] ?? null)) {
            $certificado['nome'] = $this->salvageCertificateName($certificado['nome']);
        }

        if ($rootCpf !== null) {
            $certificado['cpf'] = $rootCpf;
        }

        $node['certificado'] = $certificado;

        return $node;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function containsStaleCertificateNode(array $node): bool
    {
        foreach ($node as $value) {
            if (! is_array($value)) {
                continue;
            }

            if (isset($value['certificado']) && $this->isStaleCertificate($value['certificado'])) {
                return true;
            }

            if ($this->containsStaleCertificateNode($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function containsPendingPdfNode(array $node): bool
    {
        foreach ($node as $key => $value) {
            if (is_array($value) && $this->containsPendingPdfNode($value)) {
                return true;
            }

            if (
                is_string($key)
                && is_string($value)
                && $value !== ''
                && $this->isPdfFieldKey($key)
                && (! isset($node['certificado']) || $this->isStaleCertificate($node['certificado']))
            ) {
                return true;
            }
        }

        return false;
    }

    private function isStaleCertificate(mixed $certificado): bool
    {
        if (! is_array($certificado)) {
            return true;
        }

        $nome = $certificado['nome'] ?? null;

        if (is_string($nome) && $this->looksLikeInvalidCertificateName($nome)) {
            return true;
        }

        return ! array_key_exists('conclusao', $certificado)
            || ! array_key_exists('nadaConsta', $certificado)
            || ! array_key_exists('nrProtocolo', $certificado);
    }

    private function looksLikeInvalidCertificateName(string $nome): bool
    {
        $normalized = mb_strtolower(trim($nome));

        if (mb_strlen($normalized) > 60) {
            return true;
        }

        foreach (['filho', 'nascido', 'expedida', 'certidão', 'certidao', 'valido por', 'cpf ', 'protocolo', 'horário', 'autenticidade'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return str_starts_with($normalized, 'de ');
    }

    /**
     * @param  array<string, mixed>  $certificado
     * @return array<string, mixed>
     */
    private function sanitizeParsedCertificate(array $certificado): array
    {
        if (
            is_string($certificado['nome'] ?? null)
            && $this->looksLikeInvalidCertificateName($certificado['nome'])
        ) {
            $salvaged = $this->salvageCertificateName($certificado['nome']);
            if ($salvaged !== null) {
                $certificado['nome'] = $salvaged;
            } else {
                unset($certificado['nome']);
            }
        }

        return $certificado;
    }

    private function salvageCertificateName(string $nome): ?string
    {
        if (! $this->looksLikeInvalidCertificateName($nome)) {
            return trim($nome);
        }

        if (preg_match('/\bde\s+(.+?),\s*filh[oa]/ui', $nome, $matches) === 1) {
            $candidate = trim($matches[1]);

            if ($candidate !== '' && ! $this->looksLikeInvalidCertificateName($candidate)) {
                return mb_convert_case(mb_strtolower($candidate), MB_CASE_TITLE, 'UTF-8');
            }
        }

        if (preg_match('/\bem\s+nome\s+de\s+(.+?),\s*filh[oa]/ui', $nome, $matches) === 1) {
            $candidate = trim($matches[1]);

            if ($candidate !== '' && ! $this->looksLikeInvalidCertificateName($candidate)) {
                return mb_convert_case(mb_strtolower($candidate), MB_CASE_TITLE, 'UTF-8');
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function walk(array $node): array
    {
        foreach ($node as $key => $value) {
            if ($key === 'raw') {
                continue;
            }

            if (is_array($value)) {
                $node[$key] = $this->walk($value);

                continue;
            }

            if (! is_string($key) || ! is_string($value) || $value === '' || ! $this->isPdfFieldKey($key)) {
                continue;
            }

            if (isset($node['certificado']) && ! $this->isStaleCertificate($node['certificado'])) {
                continue;
            }

            $text = $this->extractor->extract($value);

            if ($text === null) {
                continue;
            }

            $certificado = array_filter(
                $this->sanitizeParsedCertificate($this->parser->parse($text, $this->resolveContext($key, $node))),
                static fn (mixed $entry): bool => $entry !== null && $entry !== '',
            );

            if ($certificado !== []) {
                $node['certificado'] = $certificado;
            }

            $this->mergeCertificateIntoNode($node, $certificado);
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $certificado
     */
    private function mergeCertificateIntoNode(array &$node, array $certificado): void
    {
        if (array_key_exists('nadaConsta', $certificado) && $certificado['nadaConsta'] !== null) {
            if (! array_key_exists('nadaConsta', $node) || $node['nadaConsta'] === null) {
                $node['nadaConsta'] = $certificado['nadaConsta'];
            }
        }

        if (
            isset($certificado['nrProtocolo'])
            && (! array_key_exists('nrProtocolo', $node) || $node['nrProtocolo'] === null || $node['nrProtocolo'] === '')
        ) {
            $node['nrProtocolo'] = $certificado['nrProtocolo'];
        }
    }

    private function isPdfFieldKey(string $key): bool
    {
        if (in_array($key, self::PDF_FIELD_KEYS, true)) {
            return true;
        }

        return str_ends_with(strtolower($key), 'pdfbase64');
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function resolveContext(string $key, array $node): string
    {
        if (array_key_exists('cac', $node) || array_key_exists('nrProtocolo', $node)) {
            return 'cac';
        }

        if (array_key_exists('situacaoCadastral', $node) || array_key_exists('situacao', $node)) {
            return 'situacao_cadastral';
        }

        return 'generic';
    }
}

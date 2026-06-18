<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support;

final class CertificateTextParser
{
    /**
     * @return array{
     *     tipo: string|null,
     *     conclusao: string|null,
     *     nadaConsta: bool|null,
     *     titulo: string|null,
     *     nome: string|null,
     *     nomeMae: string|null,
     *     dataNascimento: string|null,
     *     cpf: string|null,
     *     nrProtocolo: string|null,
     *     dataEmissao: string|null,
     *     validade: string|null,
     *     orgaoEmissor: string|null,
     *     textoResumo: string|null
     * }
     */
    public function parse(string $text, string $context = 'generic'): array
    {
        $text = $this->collapseWhitespace($text);
        $upper = mb_strtoupper($text);

        $nadaConsta = $this->detectNadaConsta($upper);
        $tipo = $this->detectType($upper, $context);

        return [
            'tipo' => $tipo,
            'conclusao' => $this->buildConclusao($upper, $nadaConsta),
            'nadaConsta' => $nadaConsta,
            'titulo' => $this->detectTitle($upper, $tipo),
            'nome' => $this->matchNome($text),
            'nomeMae' => $this->matchNomeMae($text),
            'dataNascimento' => $this->matchDataNascimento($text),
            'cpf' => $this->matchCpf($text),
            'nrProtocolo' => $this->matchProtocol($text),
            'dataEmissao' => $this->matchDataEmissao($text),
            'validade' => $this->matchValidade($text),
            'orgaoEmissor' => $this->detectOrgao($upper),
            'textoResumo' => $this->summarize($text),
        ];
    }

    private function detectNadaConsta(string $upper): ?bool
    {
        if (preg_match('/N[AÃ]O\s+CONSTA\s+CONDENA/u', $upper) === 1) {
            return true;
        }

        if (preg_match('/NADA\s+CONSTA/u', $upper) === 1) {
            return true;
        }

        if (preg_match('/CERTID[AÃ]O\s+NEGATIVA/u', $upper) === 1) {
            return true;
        }

        if (preg_match('/\bCONSTA\s+CONDENA/u', $upper) === 1) {
            return false;
        }

        if (preg_match('/CONSTA(?:M)?\s+(?:REGISTRO|ANTECEDENTE|OCORR)/u', $upper) === 1) {
            return false;
        }

        if (preg_match('/CERTID[AÃ]O\s+POSITIVA/u', $upper) === 1) {
            return false;
        }

        return null;
    }

    private function buildConclusao(string $upper, ?bool $nadaConsta): ?string
    {
        if ($nadaConsta === true) {
            return 'NADA CONSTA';
        }

        if ($nadaConsta === false) {
            return 'CONSTAM REGISTROS';
        }

        if (preg_match('/N[AÃ]O\s+CONSTA[^.]{0,120}/u', $upper, $matches) === 1) {
            return $this->titleCase($this->collapseWhitespace($matches[0]));
        }

        return null;
    }

    private function detectType(string $upper, string $context): ?string
    {
        if ($context === 'cac' || str_contains($upper, 'ANTECEDENTES CRIMINAIS')) {
            return 'antecedentes_criminais';
        }

        if ($context === 'situacao_cadastral' || str_contains($upper, 'SITUA')) {
            return 'situacao_cadastral';
        }

        if (str_contains($upper, 'COMPROVANTE')) {
            return 'comprovante';
        }

        return 'certificado';
    }

    private function detectTitle(string $upper, ?string $tipo): ?string
    {
        return match ($tipo) {
            'antecedentes_criminais' => 'Certidão de Antecedentes Criminais',
            'situacao_cadastral' => 'Comprovante de Situação Cadastral',
            default => null,
        };
    }

    private function detectOrgao(string $upper): ?string
    {
        foreach ([
            'POLÍCIA FEDERAL' => 'Polícia Federal',
            'POLICIA FEDERAL' => 'Polícia Federal',
            'RECEITA FEDERAL' => 'Receita Federal',
            'MINISTÉRIO DA JUSTIÇA' => 'Ministério da Justiça',
            'MINISTERIO DA JUSTICA' => 'Ministério da Justiça',
        ] as $needle => $label) {
            if (str_contains($upper, $needle)) {
                return $label;
            }
        }

        return null;
    }

    private function matchNome(string $text): ?string
    {
        if (preg_match('/\bem\s+nome\s+de\s+([A-ZÀ-Ü\'\s]+?),\s*filh[oa]/ui', $text, $matches) === 1) {
            return $this->titleCase($this->collapseWhitespace(trim($matches[1])));
        }

        if (preg_match(
            '/(?:NOME(?:\s+DO\s+CONSULTADO)?|INTERESSADO)\s*:\s*([A-ZÀ-Ü\'\s]+?)(?=\s*(?:CPF|PROTOCOLO|EMITID|VALIDADE|POL[IÍ]CIA|\z))/ui',
            $text,
            $matches,
        ) === 1) {
            return $this->titleCase($this->collapseWhitespace(trim($matches[1])));
        }

        return null;
    }

    private function matchNomeMae(string $text): ?string
    {
        if (preg_match('/filh[oa]\(?a?\)?\s+de\s+([A-ZÀ-Ü\'\s]+?),\s*nascid/ui', $text, $matches) === 1) {
            return $this->titleCase($this->collapseWhitespace(trim($matches[1])));
        }

        return null;
    }

    private function matchDataNascimento(string $text): ?string
    {
        if (preg_match('/nascid[oa]\(?a?\)?\s+aos\s+(\d{2}\/\d{2}\/\d{4})/ui', $text, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function matchCpf(string $text): ?string
    {
        if (preg_match('/\bCPF\s+(\d{3}\.\d{3}\.\d{3}-\d{2})\b/ui', $text, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/\b(\d{3}\.\d{3}\.\d{3}-\d{2})\b/u', $text, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/(?:CPF)\s*[:\-]?\s*(\d{11})/ui', $text, $matches) === 1) {
            $digits = $matches[1];

            return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
        }

        return null;
    }

    private function matchProtocol(string $text): ?string
    {
        if (preg_match('/Antecedentes\s+Criminais\s+N[°º]?\s*(\d{10,20})/ui', $text, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/n[úu]mero\s+da\s+certid[aã]o\s+(\d{10,20})/ui', $text, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/(?:PROTOCOLO|N[UÚ]MERO\s+DO\s+PROTOCOLO|NR\.?\s*PROTOCOLO)\s*[:\-]?\s*([0-9]{8,20})/ui', $text, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function matchDataEmissao(string $text): ?string
    {
        if (preg_match('/expedida\s+em\s+(\d{2}\/\d{2}\/\d{4})/ui', $text, $matches) === 1) {
            return $matches[1];
        }

        foreach (['EMITID', 'EMISS'] as $label) {
            $pattern = '/'.$label.'[^0-9]{0,20}(\d{2}\/\d{2}\/\d{4})/ui';

            if (preg_match($pattern, $text, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private function matchValidade(string $text): ?string
    {
        if (preg_match('/v[aá]lid[oa]\s+por\s+(\d+)\s+dias/ui', $text, $matches) === 1) {
            return $matches[1].' dias';
        }

        if (preg_match('/validade[^0-9]{0,20}(\d{2}\/\d{2}\/\d{4})/ui', $text, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function summarize(string $text): ?string
    {
        $collapsed = $this->collapseWhitespace($text);

        if ($collapsed === '') {
            return null;
        }

        if (mb_strlen($collapsed) <= 320) {
            return $collapsed;
        }

        return mb_substr($collapsed, 0, 317).'...';
    }

    private function collapseWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function titleCase(string $value): string
    {
        return mb_convert_case(mb_strtolower($value), MB_CASE_TITLE, 'UTF-8');
    }
}

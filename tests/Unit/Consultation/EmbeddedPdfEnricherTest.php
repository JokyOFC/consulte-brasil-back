<?php

declare(strict_types=1);

namespace Tests\Unit\Consultation;

use PHPUnit\Framework\TestCase;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\CertificateTextParser;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\EmbeddedPdfEnricher;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\EmbeddedPdfTextExtractor;

final class EmbeddedPdfEnricherTest extends TestCase
{
    public function test_it_uses_root_nome_instead_of_invalid_certificate_name(): void
    {
        $enricher = new EmbeddedPdfEnricher(new EmbeddedPdfTextExtractor, new CertificateTextParser);

        $badNome = 'De Leuton Budim, Filho(A) De Maria Lourdes Budim, Nascido(A) Aos 27/11/1972, Cpf 785.111.519-15. Esta Certidão Foi Expedida Em 18/06/2026.';

        $data = [
            'cpf' => '785.111.519-15',
            'nome' => 'Leuton Budim',
            'cac' => [
                'nrProtocolo' => '126278162026',
                'nadaConsta' => true,
                'certificado' => [
                    'tipo' => 'antecedentes_criminais',
                    'nome' => $badNome,
                    'cpf' => '785.111.519-15',
                ],
            ],
        ];

        $enriched = $enricher->enrich($data);

        $this->assertSame('Leuton Budim', $enriched['cac']['certificado']['nome']);
    }

    public function test_it_salvages_name_from_invalid_certificate_paragraph_without_root_nome(): void
    {
        $enricher = new EmbeddedPdfEnricher(new EmbeddedPdfTextExtractor, new CertificateTextParser);

        $badNome = 'De Leuton Budim, Filho(A) De Maria Lourdes Budim, Nascido(A) Aos 27/11/1972, Cpf 785.111.519-15.';

        $data = [
            'cpf' => '785.111.519-15',
            'cac' => [
                'certificado' => [
                    'nome' => $badNome,
                ],
            ],
        ];

        $enriched = $enricher->enrich($data);

        $this->assertSame('Leuton Budim', $enriched['cac']['certificado']['nome']);
    }

    public function test_it_does_not_enrich_nested_raw_payload_on_cache_reprocess(): void
    {
        $extractor = new class extends EmbeddedPdfTextExtractor
        {
            public function extract(string $base64): ?string
            {
                return 'NADA CONSTA em nome de LEUTON BUDIM, filho(a) de MARIA LOURDES BUDIM, nascido(a) aos 27/11/1972, CPF 785.111.519-15.';
            }
        };

        $enricher = new EmbeddedPdfEnricher($extractor, new CertificateTextParser);

        $data = [
            'cpf' => '785.111.519-15',
            'nome' => 'Leuton Budim',
            'cac' => [
                'comprovantePdfBase64' => 'JVBERi0fake',
                'certificado' => [
                    'nome' => 'De Leuton Budim, Filho(A) De Maria Lourdes Budim, Nascido(A) Aos 27/11/1972, Cpf 785.111.519-15.',
                ],
            ],
            'raw' => [
                'cpf' => '785.111.519-15',
                'nome' => 'Leuton Budim',
                'cac' => [
                    'comprovantePdfBase64' => 'JVBERi0fake',
                ],
            ],
        ];

        $enriched = $enricher->enrich($data);

        $this->assertSame('Leuton Budim', $enriched['cac']['certificado']['nome']);
        $this->assertArrayNotHasKey('certificado', $enriched['raw']['cac']);
    }
}

<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Enrichment;

use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\CpfCnpjHttpClient;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\CertificateTextParser;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\EmbeddedPdfEnricher;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\EmbeddedPdfTextExtractor;

final class CachedConsultationResultEnricher
{
    private EmbeddedPdfEnricher $pdfEnricher;

    public function __construct(?EmbeddedPdfEnricher $pdfEnricher = null)
    {
        $this->pdfEnricher = $pdfEnricher ?? new EmbeddedPdfEnricher(
            new EmbeddedPdfTextExtractor,
            new CertificateTextParser,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function enrich(string $providerIdentifier, array $data): array
    {
        if ($providerIdentifier !== CpfCnpjHttpClient::PROVIDER_IDENTIFIER) {
            return $data;
        }

        return $this->pdfEnricher->enrich($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function needsRefresh(array $data, bool $force = false): bool
    {
        return $this->pdfEnricher->needsRefresh($data, $force);
    }
}

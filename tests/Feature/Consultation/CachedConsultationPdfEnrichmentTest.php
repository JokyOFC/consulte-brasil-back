<?php

declare(strict_types=1);

namespace Tests\Feature\Consultation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Modules\Billing\Application\DTO\GrantCreditsInput;
use Src\Modules\Billing\Application\UseCase\GrantCredits;
use Src\Modules\Consultation\Application\DTO\CachedConsultationResult;
use Src\Modules\Consultation\Application\DTO\ExecuteConsultationInput;
use Src\Modules\Consultation\Application\Port\ConsultationResultCache;
use Src\Modules\Consultation\Application\UseCase\ExecuteConsultation;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationRequest;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;
use Src\Modules\Consultation\Infrastructure\Enrichment\CachedConsultationResultEnricher;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\CertificateTextParser;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\EmbeddedPdfEnricher;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\EmbeddedPdfTextExtractor;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Modules\Provider\Domain\Entity\Provider;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;
use Src\Modules\Provider\Domain\ValueObject\ProviderStatus;
use Src\Shared\Application\Contracts\IdGenerator;
use Tests\TestCase;

final class CachedConsultationPdfEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_hit_is_re_enriched_with_pdf_certificate_fields(): void
    {
        $accountId = app(CreateAccount::class)->handle(
            new CreateAccountInput('ACME', '11.222.333/0001-81'),
        )->id->value;

        app(GrantCredits::class)->handle(new GrantCreditsInput($accountId, 100));

        DB::table('query_types')->insert([
            'id' => app(IdGenerator::class)->generate(),
            'code' => 'cpf_cac',
            'name' => 'CPF CAC',
            'default_credit_cost' => 1,
            'cache_ttl_seconds' => 3600,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $providerId = app(IdGenerator::class)->generate();
        app(ProviderRepository::class)->save(new Provider(
            id: $providerId,
            identifier: 'cpfcnpj',
            name: 'CPF.CNPJ',
            status: ProviderStatus::Enabled,
            baseUrl: null,
            credentials: [],
        ));

        DB::table('provider_capabilities')->insert([
            'id' => app(IdGenerator::class)->generate(),
            'provider_id' => $providerId,
            'query_type' => 'cpf_cac',
            'priority' => 10,
            'price_cents' => 1,
            'enabled' => true,
            'config' => json_encode(['endpoint' => '27']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $extractor = new class extends EmbeddedPdfTextExtractor
        {
            public function extract(string $base64): ?string
            {
                return <<<'TEXT'
                CERTIDÃO DE ANTECEDENTES CRIMINAIS
                NADA CONSTA
                Nome: LEUTON BUDIM
                CPF: 785.111.519-15
                Protocolo: 126278162026
                TEXT;
            }
        };

        $this->app->instance(
            CachedConsultationResultEnricher::class,
            new CachedConsultationResultEnricher(
                new EmbeddedPdfEnricher($extractor, new CertificateTextParser),
            ),
        );

        $params = ['document' => '78511151915'];
        $request = new ConsultationRequest(
            new QueryType('cpf_cac'),
            $params,
        );
        $fingerprint = $request->fingerprint();

        app(ConsultationResultCache::class)->put(
            scope: 'production',
            queryType: 'cpf_cac',
            fingerprint: $fingerprint,
            result: new CachedConsultationResult(
                providerIdentifier: 'cpfcnpj',
                data: [
                    'cpf' => '785.111.519-15',
                    'nome' => 'Leuton Budim',
                    'cac' => [
                        'nrProtocolo' => '126278162026',
                        'comprovantePdfBase64' => 'JVBERi0fake',
                        'nadaConsta' => null,
                    ],
                ],
                httpStatus: 200,
            ),
            ttlSeconds: 3600,
        );

        $output = app(ExecuteConsultation::class)->handle(
            new ExecuteConsultationInput($accountId, null, 'cpf_cac', $params),
        );

        $this->assertTrue($output->fromCache);
        $this->assertTrue($output->data['cac']['nadaConsta']);
        $this->assertSame('NADA CONSTA', $output->data['cac']['certificado']['conclusao']);
    }
}

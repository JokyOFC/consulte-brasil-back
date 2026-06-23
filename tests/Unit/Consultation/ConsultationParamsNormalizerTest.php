<?php

declare(strict_types=1);

namespace Tests\Unit\Consultation;

use PHPUnit\Framework\TestCase;
use Src\Modules\Consultation\Application\Service\ConsultationParamsNormalizer;

final class ConsultationParamsNormalizerTest extends TestCase
{
    public function test_strips_cpf_formatting(): void
    {
        $normalizer = new ConsultationParamsNormalizer;

        $result = $normalizer->normalize(['document' => '111.444.777-35']);

        $this->assertSame(['document' => '11144477735'], $result);
    }

    public function test_preserves_alphanumeric_cnpj(): void
    {
        $normalizer = new ConsultationParamsNormalizer;

        $result = $normalizer->normalize(['document' => '12.345.678/0001-95']);

        $this->assertSame(['document' => '12345678000195'], $result);
    }

    public function test_leaves_params_without_document_unchanged(): void
    {
        $normalizer = new ConsultationParamsNormalizer;

        $params = ['razao_social' => 'ACME LTDA'];

        $this->assertSame($params, $normalizer->normalize($params));
    }
}

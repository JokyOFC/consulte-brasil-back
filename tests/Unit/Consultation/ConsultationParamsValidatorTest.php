<?php

declare(strict_types=1);

namespace Tests\Unit\Consultation;

use PHPUnit\Framework\TestCase;
use Src\Modules\Consultation\Application\Service\ConsultationParamsValidator;
use Src\Modules\Consultation\Domain\Exception\InvalidDocument;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;

final class ConsultationParamsValidatorTest extends TestCase
{
    public function test_accepts_alphanumeric_cnpj_for_cnpj_query_types(): void
    {
        $this->expectNotToPerformAssertions();

        $validator = new ConsultationParamsValidator;

        $validator->validate(new QueryType('cnpj'), ['document' => '12ABC34501DE35']);
        $validator->validate(new QueryType('ab_cnpj_receita'), ['document' => '12.abc.345/01de-35']);
    }

    public function test_accepts_numeric_cnpj_for_cnpj_query_types(): void
    {
        $this->expectNotToPerformAssertions();

        $validator = new ConsultationParamsValidator;

        $validator->validate(new QueryType('cnpj'), ['document' => '11.222.333/0001-81']);
    }

    public function test_rejects_alphanumeric_cnpj_with_wrong_check_digits(): void
    {
        $validator = new ConsultationParamsValidator;

        $this->expectException(InvalidDocument::class);

        $validator->validate(new QueryType('cnpj'), ['document' => '12ABC34501DE00']);
    }
}

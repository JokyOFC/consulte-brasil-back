<?php

declare(strict_types=1);

namespace Tests\Unit\Consultation;

use PHPUnit\Framework\TestCase;
use Src\Modules\Consultation\Application\Service\ConsultationParamsValidator;
use Src\Modules\Consultation\Domain\Exception\InvalidDocument;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;

final class ConsultationParamsValidatorCnpjExceptionTest extends TestCase
{
    private const VALID_CNPJ = '11.222.333/0001-81';

    private const VALID_CPF = '111.444.777-35';

    public function test_limite_codes_expect_cnpj_despite_cpf_prefix(): void
    {
        $validator = new ConsultationParamsValidator;

        foreach (['ab_cpf_limite', 'ab_cpf_limite_positivo'] as $code) {
            $validator->validate(new QueryType($code), ['document' => self::VALID_CNPJ]);
        }

        $this->addToAssertionCount(2);
    }

    public function test_limite_codes_reject_cpf_document(): void
    {
        $validator = new ConsultationParamsValidator;

        $this->expectException(InvalidDocument::class);

        $validator->validate(new QueryType('ab_cpf_limite'), ['document' => self::VALID_CPF]);
    }

    public function test_other_ab_cpf_codes_still_expect_cpf(): void
    {
        $validator = new ConsultationParamsValidator;

        $validator->validate(new QueryType('ab_cpf_spc_serasa'), ['document' => self::VALID_CPF]);

        $this->expectException(InvalidDocument::class);

        $validator->validate(new QueryType('ab_cpf_spc_serasa'), ['document' => self::VALID_CNPJ]);
    }
}

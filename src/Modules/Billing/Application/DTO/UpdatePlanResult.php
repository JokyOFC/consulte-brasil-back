<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\DTO;

use Src\Modules\Billing\Domain\Entity\Plan;

final readonly class UpdatePlanResult
{
    public function __construct(
        public Plan $plan,
        /** Assinaturas cujo preço congelado foi atualizado para o novo valor. */
        public int $repricedSubscriptions = 0,
        /**
         * Assinaturas no cartão cujo Preapproval não pôde ser atualizado no
         * Mercado Pago — mantidas no preço antigo para a fatura bater com a
         * cobrança real.
         */
        public int $preapprovalUpdateFailures = 0,
    ) {}
}

<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\DTO;

final readonly class UpdatePlanInput
{
    public function __construct(
        public string $planId,
        public string $name,
        public int $priceCents,
        public int $includedCredits,
        public string $billingPeriod,
        public ?int $overagePriceCents,
        public string $status,
        /**
         * Se true, o novo preço vale também para quem já assinou (repreça o
         * snapshot das assinaturas ativas). Se false (padrão), assinantes
         * atuais continuam pagando o preço congelado na contratação.
         */
        public bool $applyToExistingSubscribers = false,
    ) {}
}

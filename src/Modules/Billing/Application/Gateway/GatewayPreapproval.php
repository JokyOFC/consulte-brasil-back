<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Application\Gateway;

final readonly class GatewayPreapproval
{
    public function __construct(
        public string $mpPreapprovalId,
        public string $status,
        public ?string $initPoint = null,
    ) {}
}

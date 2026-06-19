<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Application\DTO;

final readonly class UpdateAccountWebhookInput
{
    public function __construct(
        public string $accountId,
        public ?string $webhookUrl,
        public bool $regenerateSecret = false,
    ) {}
}

<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Application\DTO;

final readonly class UpdateAccountWebhookOutput
{
    public function __construct(
        public ?string $webhookUrl,
        public bool $webhookConfigured,
        public ?string $plainSecret = null,
    ) {}
}

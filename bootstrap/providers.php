<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\MailServiceProvider;
use App\Providers\ScrambleServiceProvider;
use Src\Modules\Audit\Infrastructure\AuditServiceProvider;
use Src\Modules\Billing\Infrastructure\BillingServiceProvider;
use Src\Modules\Consultation\Infrastructure\ConsultationServiceProvider;
use Src\Modules\Identity\Infrastructure\IdentityServiceProvider;
use Src\Modules\Provider\Infrastructure\ProviderServiceProvider;
use Src\Modules\Support\Infrastructure\SupportServiceProvider;
use Src\Shared\Infrastructure\SharedServiceProvider;

return [
    AppServiceProvider::class,
    ScrambleServiceProvider::class,
    FortifyServiceProvider::class,
    MailServiceProvider::class,

    // Kernel compartilhado + módulos (bounded contexts).
    SharedServiceProvider::class,
    IdentityServiceProvider::class,
    BillingServiceProvider::class,
    ProviderServiceProvider::class,
    ConsultationServiceProvider::class,
    AuditServiceProvider::class,
    SupportServiceProvider::class,
];

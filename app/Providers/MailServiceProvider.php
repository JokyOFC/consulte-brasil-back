<?php

namespace App\Providers;

use App\Listeners\SendLoginAlertEmail;
use App\Listeners\SendWelcomeEmail;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(Registered::class, SendWelcomeEmail::class);
        Event::listen(Login::class, SendLoginAlertEmail::class);
    }
}

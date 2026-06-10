<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\WelcomeMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class WelcomeEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_welcome_email(): void
    {
        $this->skipUnlessFortifyHas('registration');

        Mail::fake();

        $this->post('/register', [
            'name' => 'ACME Ltda',
            'email' => 'welcome@acme.test',
            'phone' => '(11) 99999-9999',
            'document' => '11.222.333/0001-81',
            'password' => 'Sup3r!Secret#2026',
            'password_confirmation' => 'Sup3r!Secret#2026',
            'terms' => '1',
        ]);

        Mail::assertQueued(WelcomeMail::class, function (WelcomeMail $mail): bool {
            return $mail->hasTo('welcome@acme.test')
                && $mail->user->name === 'ACME Ltda';
        });
    }
}

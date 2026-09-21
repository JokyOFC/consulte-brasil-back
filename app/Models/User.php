<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use App\Support\AppSettings;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Src\Modules\Identity\Domain\ValueObject\Role;

#[Fillable(['name', 'email', 'phone', 'password', 'last_login_at', 'last_login_ip', 'last_login_user_agent'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function sendEmailVerificationNotification(): void
    {
        if (! $this->emailVerificationRequired()) {
            return;
        }

        $this->notify(new VerifyEmailNotification);
    }

    /**
     * Confirmação de email exigida para este usuário? Admins sempre; clientes
     * seguem a configuração geral (Admin > Configurações).
     */
    public function emailVerificationRequired(): bool
    {
        return $this->role === Role::Admin->value || AppSettings::clientEmailVerificationEnabled();
    }

    /**
     * 2FA em vigor para este usuário (desafio no login + gestão)? Admins
     * sempre; clientes seguem a configuração geral (Admin > Configurações).
     */
    public function twoFactorAvailable(): bool
    {
        return $this->role === Role::Admin->value || AppSettings::clientTwoFactorEnabled();
    }
}

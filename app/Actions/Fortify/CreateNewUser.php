<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Rules\ValidDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Modules\Identity\Domain\ValueObject\Role;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private readonly CreateAccount $createAccount) {}

    /**
     * Valida e cria um usuário recém-registrado, provisionando a Conta
     * (tenant) com o documento e, por evento, a carteira de créditos.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'phone' => ['required', 'string', 'max:30'],
            'document' => ['required', 'string', new ValidDocument],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'Você precisa aceitar os termos de uso.',
        ])->validate();

        return DB::transaction(function () use ($input): User {
            // Cria a Conta (tenant pagante) — dispara AccountRegistered,
            // que provisiona a carteira de créditos (módulo Billing).
            $account = $this->createAccount->handle(new CreateAccountInput(
                name: $input['name'],
                document: $input['document'],
            ));

            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'phone' => $input['phone'],
                'password' => $input['password'],
            ]);

            $user->forceFill([
                'account_id' => $account->id->value,
                'role' => Role::Client->value,
            ])->save();

            return $user;
        });
    }
}

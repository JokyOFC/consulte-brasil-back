<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;

#[Group('Carteira', description: 'Saldo em reais (R$) da conta autenticada.', weight: 1)]
final class CreditsController
{
    #[Endpoint(
        title: 'Consultar saldo',
        description: 'Retorna o saldo da carteira da conta associada à chave de API, em centavos de BRL: total, reservado e disponível para consultas.',
    )]
    #[Response(
        status: 200,
        description: 'Saldo da conta (valores em centavos de BRL)',
        examples: [[
            'data' => [
                'account_id' => '01932a1b-8c4d-7000-8000-000000000001',
                'currency' => 'BRL',
                'balance' => 100000,
                'reserved' => 500,
                'available' => 99500,
                'balance_formatted' => 'R$ 1.000,00',
                'reserved_formatted' => 'R$ 5,00',
                'available_formatted' => 'R$ 995,00',
            ],
        ]],
    )]
    #[Response(status: 401, description: 'Chave de API ausente, inválida ou revogada')]
    #[Response(status: 404, description: 'Carteira não encontrada para a conta')]
    public function show(Request $request, WalletRepository $wallets): JsonResponse
    {
        /** @var AccountModel $account */
        $account = $request->user();

        $wallet = $wallets->findByAccountId($account->id);

        if ($wallet === null) {
            return response()->json([
                'error' => ['type' => 'wallet_not_found', 'message' => 'Carteira não encontrada para a conta.'],
            ], 404);
        }

        return response()->json([
            'data' => [
                'account_id' => $account->id,
                'currency' => 'BRL',
                'balance' => $wallet->balance()->value,
                'reserved' => $wallet->reserved()->value,
                'available' => $wallet->available()->value,
                'balance_formatted' => $this->brl($wallet->balance()->value),
                'reserved_formatted' => $this->brl($wallet->reserved()->value),
                'available_formatted' => $this->brl($wallet->available()->value),
            ],
        ]);
    }

    private function brl(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}

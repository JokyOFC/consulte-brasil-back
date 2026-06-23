<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Http\Controllers\Client;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Audit\Infrastructure\Http\Support\RequestLogRecorder;
use Src\Modules\Billing\Domain\Exception\InsufficientCredits;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Consultation\Application\DTO\ExecuteConsultationInput;
use Src\Modules\Consultation\Application\Service\ClientConsultationCatalog;
use Src\Modules\Consultation\Application\UseCase\ExecuteConsultation;
use Src\Modules\Consultation\Domain\Exception\AllProvidersFailed;
use Src\Modules\Consultation\Domain\Exception\NoProviderAvailable;
use Src\Modules\Consultation\Domain\Exception\UnknownQueryType;
use Src\Modules\Consultation\Infrastructure\Http\Requests\ExecuteConsultationWebRequest;
use Throwable;

/**
 * Painel do cliente: catálogo de consultas e execução via sessão web.
 */
final class ConsultationsClientController
{
    public function __construct(
        private RequestLogRecorder $requestLogRecorder,
    ) {}

    public function index(Request $request, ClientConsultationCatalog $catalog, WalletRepository $wallets): Response
    {
        $accountId = $request->user()->account_id;
        $wallet = $accountId !== null ? $wallets->findByAccountId($accountId) : null;

        return Inertia::render('client/consultations/index', [
            'query_types' => $catalog->list(),
            'wallet' => $wallet === null ? null : [
                'balance' => $wallet->balance()->value,
                'reserved' => $wallet->reserved()->value,
                'available' => $wallet->available()->value,
            ],
            'result' => $request->session()->get('consultation_result'),
            'selected_query_type' => $request->session()->get('selected_query_type'),
        ]);
    }

    public function store(
        string $queryType,
        ExecuteConsultationWebRequest $request,
        ExecuteConsultation $execute,
    ): RedirectResponse {
        abort_if($request->user()->account_id === null, 422, 'User has no account.');

        $startedAt = microtime(true);
        $body = $request->validated();

        try {
            $output = $execute->handle(new ExecuteConsultationInput(
                accountId: $request->user()->account_id,
                apiKeyId: null,
                queryType: $queryType,
                params: $request->validatedParams(),
            ));
        } catch (InsufficientCredits) {
            $this->logWebConsultation(
                $request,
                $queryType,
                $startedAt,
                402,
                false,
                $body,
                ['error' => ['type' => 'insufficient_credits', 'message' => 'Saldo insuficiente.']],
            );

            return redirect()
                ->route('client.consultations.index')
                ->with('error', 'Saldo insuficiente. Recarregue sua carteira para continuar.')
                ->with('selected_query_type', $queryType);
        } catch (UnknownQueryType) {
            abort(404);
        } catch (AllProvidersFailed|NoProviderAvailable $e) {
            $message = $e instanceof AllProvidersFailed && $e->userMessage !== null
                ? $e->userMessage
                : 'Não foi possível concluir a consulta. Nenhum valor foi debitado.';

            $this->logWebConsultation(
                $request,
                $queryType,
                $startedAt,
                503,
                false,
                $body,
                ['error' => ['type' => 'all_providers_failed', 'message' => $message]],
            );

            return redirect()
                ->route('client.consultations.index')
                ->with('error', $message)
                ->with('selected_query_type', $queryType);
        } catch (Throwable $e) {
            $this->logWebConsultation(
                $request,
                $queryType,
                $startedAt,
                500,
                false,
                $body,
                ['error' => ['type' => 'internal_error', 'message' => 'Erro ao processar consulta.']],
            );

            throw $e;
        }

        $responseSummary = $this->requestLogRecorder->buildConsultationResponseSummary(
            $output->consultationId,
            $output->providerIdentifier,
            $output->creditsCharged,
            $output->fromCache,
            $output->data,
        );

        $this->logWebConsultation(
            $request,
            $queryType,
            $startedAt,
            200,
            true,
            $body,
            $responseSummary,
            $output->consultationId,
        );

        return redirect()
            ->route('client.consultations.index')
            ->with([
                'success' => 'Consulta realizada com sucesso.',
                'selected_query_type' => $queryType,
                'consultation_result' => [
                    'consultation_id' => $output->consultationId,
                    'query_type' => $queryType,
                    'amount_charged' => $output->creditsCharged,
                    'from_cache' => $output->fromCache,
                    'data' => $output->data,
                ],
            ]);
    }

    /** @param array<string, mixed> $body */
    /** @param array<string, mixed> $responseSummary */
    private function logWebConsultation(
        Request $request,
        string $queryType,
        float $startedAt,
        int $statusCode,
        bool $success,
        array $body,
        array $responseSummary,
        ?string $consultationId = null,
    ): void {
        try {
            $this->requestLogRecorder->recordWebConsultation(
                $request,
                $queryType,
                $statusCode,
                $success,
                $body,
                $responseSummary,
                $consultationId,
                $startedAt,
            );
        } catch (Throwable) {
            // Auditoria não pode interromper a consulta.
        }
    }
}

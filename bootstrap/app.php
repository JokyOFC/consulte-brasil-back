<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Src\Modules\Audit\Infrastructure\Http\Middleware\LogRequest;
use Src\Modules\Billing\Domain\Exception\InsufficientCredits;
use Src\Modules\Consultation\Domain\Exception\AllProvidersFailed;
use Src\Modules\Consultation\Domain\Exception\InvalidDocument;
use Src\Modules\Consultation\Domain\Exception\NoProviderAvailable;
use Src\Modules\Consultation\Domain\Exception\UnknownQueryType;
use Src\Shared\Domain\Exception\DomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        if (($proxies = env('TRUSTED_PROXIES')) !== null && $proxies !== '') {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        $middleware->encryptCookies(except: ['appearance', 'theme', 'sidebar_state']);

        $middleware->web(append: [
            AddSecurityHeaders::class,
            EnforceSessionTimeout::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Trilha de auditoria de toda a API pública (corpo, headers, status…).
        $middleware->api(prepend: [
            AddSecurityHeaders::class,
        ]);
        $middleware->api(append: [
            LogRequest::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Tradução das exceções de domínio → códigos HTTP + envelope padronizado.

        // Documento inválido (CPF/CNPJ) → 422 com mensagem amigável ao usuário.
        $exceptions->render(function (InvalidDocument $e, Request $request): ?JsonResponse {
            return $request->is('api/*')
                ? response()->json(['error' => ['type' => 'invalid_document', 'message' => $e->userMessage]], 422)
                : null;
        });

        $exceptions->render(function (InsufficientCredits $e, Request $request): ?JsonResponse {
            return $request->is('api/*')
                ? response()->json(['error' => ['type' => 'insufficient_credits', 'message' => $e->getMessage()]], 402)
                : null;
        });

        $exceptions->render(function (NoProviderAvailable $e, Request $request): ?JsonResponse {
            return $request->is('api/*')
                ? response()->json(['error' => ['type' => 'no_provider_available', 'message' => $e->getMessage()]], 503)
                : null;
        });

        $exceptions->render(function (AllProvidersFailed $e, Request $request): ?JsonResponse {
            return $request->is('api/*')
                ? response()->json(['error' => ['type' => 'all_providers_failed', 'message' => $e->getMessage()]], 503)
                : null;
        });

        $exceptions->render(function (UnknownQueryType $e, Request $request): ?JsonResponse {
            return $request->is('api/*')
                ? response()->json(['error' => ['type' => 'unknown_query_type', 'message' => $e->getMessage()]], 404)
                : null;
        });

        // Rede de segurança: qualquer exceção de domínio não tratada acima
        // vira resposta amigável — JSON 422 na API; flash de erro (→ toast)
        // no web — em vez de uma tela de 500.
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => ['type' => 'domain_error', 'message' => $e->getMessage()],
                ], 422);
            }

            return back()->with(
                'error',
                'Não foi possível concluir a operação. Verifique os dados informados e tente novamente.',
            );
        });
    })->create();

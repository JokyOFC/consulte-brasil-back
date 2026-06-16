<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Src\Modules\Billing\Application\UseCase\HandleMercadoPagoWebhook;

/**
 * Recebe as notificações (webhook) do Mercado Pago. Rota pública (sem
 * auth:api): a autenticidade é garantida pela validação da assinatura
 * x-signature (HMAC do segredo do webhook).
 */
#[ExcludeAllRoutesFromDocs]
final class MercadoPagoWebhookController
{
    private const SIGNATURE_MAX_AGE_SECONDS = 300;

    public function __invoke(Request $request, HandleMercadoPagoWebhook $handler): JsonResponse
    {
        $type = (string) ($request->input('type') ?? $request->query('type') ?? $request->query('topic') ?? '');
        $dataId = (string) (
            $request->input('data.id')
            ?? $request->query('data_id')
            ?? $request->query('id')
            ?? ''
        );

        if ($dataId === '') {
            return response()->json(['received' => true]);
        }

        if (! $this->signatureValid($request, $dataId)) {
            Log::warning('mp.webhook.invalid_signature', ['data_id' => $dataId]);

            return response()->json(['error' => 'invalid signature'], 401);
        }

        $handler->handle($type !== '' ? $type : 'payment', $dataId);

        return response()->json(['received' => true]);
    }

    /**
     * Valida a assinatura x-signature conforme a doc do Mercado Pago:
     * manifest = "id:{dataId};request-id:{x-request-id};ts:{ts};" e
     * HMAC-SHA256 com o webhook_secret. Sem segredo configurado (sandbox),
     * a validação é ignorada.
     */
    private function signatureValid(Request $request, string $dataId): bool
    {
        $secret = (string) config('services.mercado_pago.webhook_secret', '');
        if ($secret === '') {
            return ! app()->environment('production');
        }

        $signature = (string) $request->header('x-signature', '');
        $requestId = (string) $request->header('x-request-id', '');

        $parts = [];
        foreach (explode(',', $signature) as $piece) {
            $kv = explode('=', trim($piece), 2);
            if (count($kv) === 2) {
                $parts[trim($kv[0])] = trim($kv[1]);
            }
        }

        $ts = $parts['ts'] ?? null;
        $v1 = $parts['v1'] ?? null;
        if ($ts === null || $v1 === null || ! is_numeric($ts)) {
            return false;
        }

        if (abs(time() - (int) $ts) > self::SIGNATURE_MAX_AGE_SECONDS) {
            return false;
        }

        $manifest = sprintf('id:%s;request-id:%s;ts:%s;', strtolower($dataId), $requestId, $ts);
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $v1);
    }
}

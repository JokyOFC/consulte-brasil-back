<?php

declare(strict_types=1);

namespace Src\Modules\Audit\Infrastructure\Http\Support;

use App\Support\Dates;
use Illuminate\Support\Carbon;
use Src\Modules\Audit\Infrastructure\Persistence\Eloquent\Models\RequestLogModel;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models\ConsultationModel;

/**
 * Enriquece request_logs para exibição no admin: metadados da consulta,
 * mensagem de erro, tipo extraído da rota, etc.
 */
final class RequestLogPresenter
{
    /**
     * @param  array<string, mixed>|null  $consultation
     * @return array<string, mixed>
     */
    public function present(
        RequestLogModel $log,
        ?string $accountName = null,
        ?string $apiKeyName = null,
        ?array $consultation = null,
    ): array {
        $response = is_array($log->response) ? $log->response : [];
        $meta = $this->extractResponseMeta($response);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $queryType = $this->extractQueryType($log->path) ?? ($consultation['query_type'] ?? null);

        return [
            'id' => $log->id,
            'account_id' => $log->account_id,
            'account_name' => $accountName,
            'api_key_id' => $log->api_key_id,
            'api_key_name' => $apiKeyName,
            'method' => $log->method,
            'path' => $log->path,
            'route_name' => $log->route_name,
            'query_type' => $queryType,
            'status_code' => $log->status_code,
            'success' => $log->success,
            'duration_ms' => $log->duration_ms,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'query' => $log->query,
            'headers' => $log->headers,
            'body' => $log->body,
            'response' => $log->response,
            'consultation_id' => $log->consultation_id
                ?? ($consultation['id'] ?? null)
                ?? (is_string($data['consultation_id'] ?? null) ? $data['consultation_id'] : null),
            'consultation_status' => $consultation['status'] ?? null,
            'consultation_latency_ms' => $consultation['latency_ms'] ?? null,
            'from_cache' => $meta['from_cache'],
            'provider' => $meta['provider'] ?? ($consultation['provider'] ?? null),
            'amount_charged' => $meta['amount_charged'] ?? ($consultation['credit_cost'] ?? null),
            'error_type' => $meta['error_type'],
            'error_message' => $meta['error_message'],
            'response_truncated' => $meta['response_truncated'],
            'created_at' => Dates::toFrontendIso($log->created_at),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveConsultationForLog(RequestLogModel $log): ?array
    {
        if (is_string($log->consultation_id) && $log->consultation_id !== '') {
            $byId = $this->loadConsultationsById([$log->consultation_id]);

            return $byId[$log->consultation_id] ?? null;
        }

        if ($log->account_id === null || $log->created_at === null) {
            return null;
        }

        $queryType = $this->extractQueryType($log->path);
        if ($queryType === null) {
            return null;
        }

        $createdAt = Carbon::parse($log->created_at);

        $consultation = ConsultationModel::query()
            ->leftJoin('providers', 'consultations.provider_id', '=', 'providers.id')
            ->where('consultations.account_id', $log->account_id)
            ->where('consultations.query_type', $queryType)
            ->whereBetween('consultations.created_at', [
                $createdAt->copy()->subSeconds(5),
                $createdAt->copy()->addSeconds(5),
            ])
            ->orderByDesc('consultations.created_at')
            ->first([
                'consultations.id',
                'consultations.status',
                'consultations.query_type',
                'consultations.latency_ms',
                'consultations.credit_cost',
                'providers.identifier as provider',
            ]);

        return $this->mapConsultationRow($consultation);
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array<string, mixed>>
     */
    private function loadConsultationsById(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return ConsultationModel::query()
            ->leftJoin('providers', 'consultations.provider_id', '=', 'providers.id')
            ->whereIn('consultations.id', $ids)
            ->get([
                'consultations.id',
                'consultations.status',
                'consultations.query_type',
                'consultations.latency_ms',
                'consultations.credit_cost',
                'providers.identifier as provider',
            ])
            ->mapWithKeys(fn ($row) => [
                (string) $row->id => $this->mapConsultationRow($row),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapConsultationRow(mixed $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'id' => (string) $row->id,
            'status' => (string) $row->status,
            'query_type' => (string) $row->query_type,
            'latency_ms' => $row->latency_ms !== null ? (int) $row->latency_ms : null,
            'credit_cost' => (int) $row->credit_cost,
            'provider' => $row->provider !== null ? (string) $row->provider : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{
     *     from_cache: bool|null,
     *     provider: string|null,
     *     amount_charged: int|null,
     *     error_type: string|null,
     *     error_message: string|null,
     *     response_truncated: bool
     * }
     */
    private function extractResponseMeta(array $response): array
    {
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $error = is_array($response['error'] ?? null) ? $response['error'] : [];

        $errorMessage = null;
        if (is_string($error['message'] ?? null) && $error['message'] !== '') {
            $errorMessage = $error['message'];
        } elseif (is_string($response['message'] ?? null) && $response['message'] !== '') {
            $errorMessage = $response['message'];
        }

        return [
            'from_cache' => isset($data['from_cache']) ? (bool) $data['from_cache'] : null,
            'provider' => is_string($data['provider'] ?? null) ? $data['provider'] : null,
            'amount_charged' => isset($data['amount_charged']) ? (int) $data['amount_charged'] : null,
            'error_type' => is_string($error['type'] ?? null) ? $error['type'] : null,
            'error_message' => $errorMessage,
            'response_truncated' => ($data['_audit_truncated'] ?? false) === true,
        ];
    }

    private function extractQueryType(string $path): ?string
    {
        if (preg_match('#/api/v1/consult/([^/?]+)#', $path, $matches) !== 1) {
            return null;
        }

        return urldecode($matches[1]);
    }
}

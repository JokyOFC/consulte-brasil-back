<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Src\Modules\Consultation\Infrastructure\Webhook\WebhookSignatureGenerator;

final class DeliverConsultationWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $webhookUrl,
        public string $webhookSecret,
        public array $payload,
    ) {}

    public function handle(HttpFactory $http, WebhookSignatureGenerator $signer): void
    {
        $body = json_encode($this->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signed = $signer->generate($body, $this->webhookSecret);

        try {
            $response = $http->timeout(10)
                ->withHeaders([
                    WebhookSignatureGenerator::HEADER_NAME => $signed['header'],
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'ConsulteBrasil-Webhook/1.0',
                ])
                ->withBody($body, 'application/json')
                ->post($this->webhookUrl);

            if (! $response->successful()) {
                Log::channel('consultation')->warning('consultation.webhook.delivery_failed', [
                    'consultation_id' => $this->payload['consultation_id'] ?? null,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $this->fail(new \RuntimeException('Webhook returned HTTP '.$response->status()));
            }
        } catch (\Throwable $e) {
            Log::channel('consultation')->warning('consultation.webhook.delivery_error', [
                'consultation_id' => $this->payload['consultation_id'] ?? null,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

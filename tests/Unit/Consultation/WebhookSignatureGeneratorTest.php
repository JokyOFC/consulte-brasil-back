<?php

declare(strict_types=1);

namespace Tests\Unit\Consultation;

use Src\Modules\Consultation\Infrastructure\Webhook\WebhookSignatureGenerator;
use Tests\TestCase;

final class WebhookSignatureGeneratorTest extends TestCase
{
    public function test_generates_header_that_verifies_against_payload(): void
    {
        $signer = new WebhookSignatureGenerator;
        $payload = '{"event":"consultation.completed","status":"success"}';
        $secret = 'super-secret-key';

        $signed = $signer->generate($payload, $secret, timestamp: 1_700_000_000);

        $this->assertSame('t=1700000000,v1='.hash_hmac('sha256', '1700000000.'.$payload, $secret), $signed['header']);
        $this->assertTrue($signer->verify($payload, $secret, $signed['header'], now: 1_700_000_000));
    }

    public function test_rejects_stale_timestamp(): void
    {
        $signer = new WebhookSignatureGenerator;
        $payload = '{"event":"consultation.completed"}';
        $secret = 'secret';
        $signed = $signer->generate($payload, $secret, timestamp: 1_700_000_000);

        $this->assertFalse($signer->verify($payload, $secret, $signed['header'], now: 1_700_000_400));
    }

    public function test_rejects_invalid_signature(): void
    {
        $signer = new WebhookSignatureGenerator;
        $payload = '{"event":"consultation.completed"}';

        $this->assertFalse($signer->verify($payload, 'secret', 't=1700000000,v1=deadbeef', now: 1_700_000_000));
    }
}

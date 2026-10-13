<?php

namespace App\Services\Notifications;

/**
 * Outbound delivery adapter. Tests bind a fake; production uses env/config without exposing secrets.
 */
class MessageGatewayDriver
{
    /** @var list<array<string, mixed>> */
    public array $sent = [];

    public bool $shouldFail = false;

    public string $failureReason = 'Provider rejected message.';

    /**
     * @param  array{channel: string, to: string, subject?: ?string, body: string, credentials?: array<string, mixed>}  $payload
     * @return array{ok: bool, reference?: string, error?: string}
     */
    public function send(array $payload): array
    {
        if ($this->shouldFail) {
            return ['ok' => false, 'error' => $this->failureReason];
        }

        $reference = strtoupper($payload['channel']).'-'.uniqid();
        $this->sent[] = [
            'channel' => $payload['channel'],
            'to' => $payload['to'],
            'subject' => $payload['subject'] ?? null,
            'body' => $payload['body'],
            'reference' => $reference,
        ];

        return ['ok' => true, 'reference' => $reference];
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function testConnection(string $channel, array $credentials): array
    {
        $has = filled($credentials['api_key'] ?? null)
            || filled($credentials['token'] ?? null)
            || filled($credentials['password'] ?? null)
            || filled($credentials['host'] ?? null);

        if (! $has) {
            return ['ok' => false, 'message' => 'Gateway unconfigured: credentials missing.'];
        }

        return ['ok' => true, 'message' => 'Connection check succeeded (sandbox).'];
    }
}

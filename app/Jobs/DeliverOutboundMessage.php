<?php

namespace App\Jobs;

use App\Models\Csms\MessageLog;
use App\Services\Notifications\MessageGatewayDriver;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeliverOutboundMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $messageLogId) {}

    public function handle(MessageGatewayDriver $driver, TenantContext $tenant): void
    {
        $tenant->bypass(function () use ($driver): void {
            $log = MessageLog::query()->find($this->messageLogId);
            if (! $log || in_array($log->status, ['sent', 'delivered'], true)) {
                return;
            }

            $log->increment('attempts');
            $result = $driver->send([
                'channel' => $log->channel,
                'to' => (string) $log->recipient_reference,
                'subject' => $log->subject,
                'body' => (string) $log->body_preview,
            ]);

            if ($result['ok'] ?? false) {
                $log->update([
                    'status' => 'sent',
                    'provider_reference' => $result['reference'] ?? null,
                    'sent_at' => now(),
                    'failure_reason' => null,
                ]);

                return;
            }

            $log->update([
                'status' => 'failed',
                'failure_reason' => $result['error'] ?? 'Delivery failed',
            ]);
            Log::warning('Outbound message failed', [
                'message_log_id' => $log->id,
                'channel' => $log->channel,
                // never log credentials or full provider payloads
            ]);
        });
    }
}

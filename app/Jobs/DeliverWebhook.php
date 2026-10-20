<?php

namespace App\Jobs;

use App\Domain\Notifications\WebhookDispatcher;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @param array<string, mixed> $body */
    public function __construct(public int $endpointId, public array $body) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 300];
    }

    public function handle(): void
    {
        $endpoint = WebhookEndpoint::query()->find($this->endpointId);
        if ($endpoint === null || ! $endpoint->is_active) {
            return;
        }

        $json = (string) json_encode($this->body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;

        try {
            $response = Http::timeout(10)
                ->withUserAgent('vigilo-webhook/'.config('vigilo.version'))
                ->withHeaders([
                    'X-Vigilo-Event' => (string) ($this->body['event'] ?? 'unknown'),
                    'X-Vigilo-Timestamp' => $timestamp,
                    'X-Vigilo-Delivery' => (string) Str::uuid(),
                    'X-Vigilo-Signature' => WebhookDispatcher::sign((string) $endpoint->secret, $timestamp, $json),
                ])
                ->withBody($json, 'application/json')
                ->post($endpoint->url);
        } catch (Throwable $e) {
            $endpoint->forceFill(['last_status' => null, 'last_error' => mb_substr($e->getMessage(), 0, 500)])->save();

            throw $e;
        }

        $endpoint->forceFill([
            'last_status' => $response->status(),
            'last_delivered_at' => $response->successful() ? now() : $endpoint->last_delivered_at,
            'last_error' => $response->successful() ? null : mb_substr('HTTP '.$response->status().': '.$response->body(), 0, 500),
        ])->save();

        if (! $response->successful()) {
            throw new RuntimeException('Webhook endpoint answered HTTP '.$response->status());
        }
    }
}

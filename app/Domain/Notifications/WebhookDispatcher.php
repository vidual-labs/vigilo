<?php

namespace App\Domain\Notifications;

use App\Jobs\DeliverWebhook;
use App\Models\WebhookEndpoint;

/**
 * Outgoing webhooks: JSON POST, HMAC-SHA256 signed. Receivers verify
 *   X-Vigilo-Signature: sha256=hex(hmac_sha256(secret, "<X-Vigilo-Timestamp>.<raw body>"))
 * and should reject timestamps older than 5 minutes.
 */
class WebhookDispatcher
{
    /** @param array<string, mixed> $data */
    public function dispatch(string $event, array $data, string $subscription): int
    {
        $endpoints = WebhookEndpoint::query()->active()->where('send_'.$subscription, true)->get();
        foreach ($endpoints as $endpoint) {
            DeliverWebhook::dispatch($endpoint->id, self::body($event, $data));
        }

        return $endpoints->count();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function body(string $event, array $data): array
    {
        return [
            'event' => $event,
            'sent_at' => now()->toIso8601String(),
            'vigilo_version' => config('vigilo.version'),
            ...$data,
        ];
    }

    public static function sign(string $secret, string $timestamp, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}

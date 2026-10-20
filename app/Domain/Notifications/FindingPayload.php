<?php

namespace App\Domain\Notifications;

use App\Models\Finding;

/** JSON shape of a finding in webhook payloads (stable; documented in README). */
final class FindingPayload
{
    /** @return array<string, mixed> */
    public static function make(Finding $finding): array
    {
        $finding->loadMissing('client');

        return [
            'id' => $finding->id,
            'client' => ['id' => $finding->client_id, 'name' => $finding->client?->name],
            'check' => $finding->check_key,
            'severity' => $finding->severity->value,
            'status' => $finding->status->value,
            'platform' => $finding->platform?->value,
            'title' => $finding->title('en'),
            'title_key' => $finding->title_key,
            'title_params' => $finding->title_params,
            'dedupe_key' => $finding->dedupe_key,
            'first_detected_at' => $finding->first_detected_at->toIso8601String(),
            'last_detected_at' => $finding->last_detected_at->toIso8601String(),
            'opened_at' => $finding->opened_at->toIso8601String(),
            'url' => route('findings.show', $finding),
        ];
    }
}

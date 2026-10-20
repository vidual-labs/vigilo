<?php

namespace App\Domain\Findings\Services;

use App\Domain\Checks\CheckResult;
use App\Domain\Findings\Data\FindingDraft;
use App\Domain\Findings\Enums\FindingEventType;
use App\Domain\Findings\Enums\FindingStatus;
use App\Domain\Findings\Enums\Severity;
use App\Models\Client;
use App\Models\Finding;
use App\Models\FindingEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Turns a check's result into finding rows — the dedupe and lifecycle
 * rules live here and only here:
 *
 * - new dedupe key → open finding
 * - seen again → refreshed (title, evidence, last_detected_at); no new alert
 * - severity raised → escalated (re-opens an acknowledged finding)
 * - resolved and seen again → reopened (alerts again: it is a new incident)
 * - snoozed past snoozed_until and still seen → open again
 * - active but not reported (and not "kept") → auto-resolved
 */
class FindingReconciler
{
    /**
     * @return array{opened: list<Finding>, escalated: list<Finding>, resolved: list<Finding>}
     */
    public function reconcile(Client $client, string $checkKey, CheckResult $result, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $changes = ['opened' => [], 'escalated' => [], 'resolved' => []];

        DB::transaction(function () use ($client, $checkKey, $result, $now, &$changes) {
            $seen = [];
            foreach ($result->findings as $draft) {
                if (isset($seen[$draft->dedupeKey])) {
                    continue; // a check reporting the same key twice keeps the first
                }
                $seen[$draft->dedupeKey] = true;
                $this->apply($client, $draft, $now, $changes);
            }

            $stale = Finding::query()
                ->where('client_id', $client->id)
                ->where('check_key', $checkKey)
                ->active()
                ->whereNotIn('dedupe_key', array_keys($seen) ?: [''])
                ->get();

            foreach ($stale as $finding) {
                if ($result->keeps($finding->dedupe_key)) {
                    continue;
                }
                $finding->forceFill([
                    'status' => FindingStatus::Resolved,
                    'resolved_at' => $now,
                    'resolved_by' => null,
                    'snoozed_until' => null,
                ])->save();
                $this->event($finding, FindingEventType::Resolved, $now, ['auto' => true]);
                $changes['resolved'][] = $finding;
            }
        });

        return $changes;
    }

    /** @param array{opened: list<Finding>, escalated: list<Finding>, resolved: list<Finding>} $changes */
    private function apply(Client $client, FindingDraft $draft, CarbonImmutable $now, array &$changes): void
    {
        $finding = Finding::query()->where('dedupe_key', $draft->dedupeKey)->lockForUpdate()->first();
        $content = [
            'title_key' => $draft->titleKey,
            'title_params' => $draft->titleParams,
            'evidence' => $draft->evidence,
            'platform' => $draft->platform,
            'last_detected_at' => $now,
        ];

        if ($finding === null) {
            $finding = Finding::query()->create([
                ...$content,
                'client_id' => $client->id,
                'check_key' => $draft->checkKey,
                'dedupe_key' => $draft->dedupeKey,
                'severity' => $draft->severity,
                'status' => FindingStatus::Open,
                'first_detected_at' => $now,
                'opened_at' => $now,
            ]);
            $this->event($finding, FindingEventType::Opened, $now, ['severity' => $draft->severity->value]);
            $changes['opened'][] = $finding;

            return;
        }

        $previous = $finding->severity;
        $finding->fill($content);
        $finding->occurrences++;

        if ($finding->status === FindingStatus::Resolved) {
            $finding->forceFill([
                'status' => FindingStatus::Open,
                'severity' => $draft->severity,
                'opened_at' => $now,
                'resolved_at' => null,
                'resolved_by' => null,
                'acknowledged_at' => null,
                'acknowledged_by' => null,
                'notified_at' => null,
                'notified_severity' => null,
            ]);
            $finding->reopen_count++;
            $finding->save();
            $this->event($finding, FindingEventType::Reopened, $now, ['severity' => $draft->severity->value]);
            $changes['opened'][] = $finding;

            return;
        }

        if ($finding->status === FindingStatus::Snoozed && $finding->snoozed_until?->lessThanOrEqualTo($now)) {
            $finding->forceFill(['status' => FindingStatus::Open, 'snoozed_until' => null]);
            $this->event($finding, FindingEventType::Unsnoozed, $now);
        }

        if ($draft->severity->isWorseThan($previous)) {
            $finding->severity = $draft->severity;
            // Getting worse ends an acknowledgement or a snooze.
            if (in_array($finding->status, [FindingStatus::Acknowledged, FindingStatus::Snoozed], true)) {
                $finding->status = FindingStatus::Open;
                $finding->snoozed_until = null;
            }
            $finding->save();
            $this->event($finding, FindingEventType::Escalated, $now, ['from' => $previous->value, 'to' => $draft->severity->value]);
            $changes['escalated'][] = $finding;

            return;
        }

        if ($previous->isWorseThan($draft->severity)) {
            $finding->severity = $draft->severity;
            $this->event($finding, FindingEventType::Deescalated, $now, ['from' => $previous->value, 'to' => $draft->severity->value]);
        }

        $finding->save();
    }

    /** @param array<string, mixed> $data */
    public function event(Finding $finding, FindingEventType $type, ?CarbonImmutable $at = null, array $data = [], ?int $userId = null): void
    {
        FindingEvent::query()->create([
            'finding_id' => $finding->id,
            'type' => $type,
            'user_id' => $userId,
            'data' => $data ?: null,
            'created_at' => $at ?? now(),
        ]);
    }

    /** Worst severity among findings; used for notification decisions. */
    public static function needsImmediateAlert(Finding $finding): bool
    {
        return $finding->severity === Severity::Critical
            && $finding->status === FindingStatus::Open
            && ($finding->notified_severity === null || $finding->severity->isWorseThan($finding->notified_severity));
    }
}

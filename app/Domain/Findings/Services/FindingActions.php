<?php

namespace App\Domain\Findings\Services;

use App\Domain\Findings\Enums\FindingEventType;
use App\Domain\Findings\Enums\FindingStatus;
use App\Models\Finding;
use App\Models\User;
use Carbon\CarbonImmutable;

/** What a person can do with a finding. Every action leaves an event. */
class FindingActions
{
    public const SNOOZE_DAYS = [1, 7, 30];

    public function __construct(private readonly FindingReconciler $reconciler) {}

    public function acknowledge(Finding $finding, User $user): void
    {
        if (! $finding->status->isActive()) {
            return;
        }
        $finding->forceFill([
            'status' => FindingStatus::Acknowledged,
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
            'snoozed_until' => null,
        ])->save();
        $this->reconciler->event($finding, FindingEventType::Acknowledged, userId: $user->id);
    }

    public function snooze(Finding $finding, User $user, int $days): void
    {
        if (! $finding->status->isActive()) {
            return;
        }
        $until = CarbonImmutable::now()->addDays(max(1, min(90, $days)));
        $finding->forceFill(['status' => FindingStatus::Snoozed, 'snoozed_until' => $until])->save();
        $this->reconciler->event($finding, FindingEventType::Snoozed, data: ['until' => $until->toIso8601String()], userId: $user->id);
    }

    /**
     * Manual resolve. If the problem is still there, the next run reopens
     * it — acknowledge or snooze to silence a known, ongoing problem.
     */
    public function resolve(Finding $finding, User $user): void
    {
        if ($finding->status === FindingStatus::Resolved) {
            return;
        }
        $finding->forceFill([
            'status' => FindingStatus::Resolved,
            'resolved_at' => now(),
            'resolved_by' => $user->id,
            'snoozed_until' => null,
        ])->save();
        $this->reconciler->event($finding, FindingEventType::Resolved, data: ['auto' => false], userId: $user->id);
    }

    /** Back to open (undo acknowledge/snooze). */
    public function reopen(Finding $finding, User $user): void
    {
        if (! in_array($finding->status, [FindingStatus::Acknowledged, FindingStatus::Snoozed], true)) {
            return;
        }
        $finding->forceFill(['status' => FindingStatus::Open, 'snoozed_until' => null, 'acknowledged_at' => null, 'acknowledged_by' => null])->save();
        $this->reconciler->event($finding, FindingEventType::Unsnoozed, userId: $user->id);
    }
}

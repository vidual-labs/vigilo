<?php

namespace App\Domain\Notifications;

use App\Domain\Findings\Enums\FindingStatus;
use App\Domain\Findings\Enums\Severity;
use App\Models\Client;
use App\Models\Finding;
use App\Models\User;
use Carbon\CarbonImmutable;

/** What one user's daily digest contains: active findings, critical first. */
class DigestBuilder
{
    public const MAX_PER_SEVERITY = 50;

    /**
     * @return array{
     *   groups: array<string, list<Finding>>,
     *   more: array<string, int>,
     *   counts: array<string, int>,
     *   new: int,
     *   clients_with_findings: int,
     *   clients_total: int,
     *   since: CarbonImmutable,
     * }
     */
    public function build(User $user, ?CarbonImmutable $since = null): array
    {
        $since ??= CarbonImmutable::now()->subDay();

        $findings = Finding::query()
            ->visibleTo($user)
            ->whereIn('status', [FindingStatus::Open->value, FindingStatus::Acknowledged->value])
            ->with('client')
            ->orderBySeverity()
            ->orderByDesc('opened_at')
            ->get();

        $groups = [];
        $more = [];
        $counts = [];
        foreach (Severity::orderedValues() as $severity) {
            $all = $findings->filter(fn (Finding $f) => $f->severity->value === $severity)
                ->sortBy([fn (Finding $a, Finding $b) => ($b->isNewSince($since) <=> $a->isNewSince($since)), fn (Finding $a, Finding $b) => strcmp((string) $a->client?->name, (string) $b->client?->name)])
                ->values();
            $counts[$severity] = $all->count();
            $groups[$severity] = $all->take(self::MAX_PER_SEVERITY)->all();
            $more[$severity] = max(0, $all->count() - self::MAX_PER_SEVERITY);
        }

        return [
            'groups' => $groups,
            'more' => $more,
            'counts' => $counts,
            'new' => $findings->filter(fn (Finding $f) => $f->isNewSince($since))->count(),
            'clients_with_findings' => $findings->pluck('client_id')->unique()->count(),
            'clients_total' => Client::query()->visibleTo($user)->active()->count(),
            'since' => $since,
        ];
    }
}

<?php

namespace App\Domain\Checks\Checks;

use App\Domain\Checks\CheckResult;
use App\Domain\Checks\Snapshot\ClientSnapshot;
use App\Domain\Findings\Enums\Severity;

/**
 * Ads keep spending but the lead source has received nothing for N hours —
 * a broken form, webhook or sync between the landing page and lodgely.
 * Needs a lead source; clients without one are skipped.
 */
class SpendWithoutLeadsCheck extends BaseCheck
{
    public function key(): string
    {
        return 'spend_without_leads';
    }

    public function fields(): array
    {
        return ['hours' => 'int', 'min_spend' => 'float'];
    }

    public function run(ClientSnapshot $snapshot, array $thresholds): CheckResult
    {
        $result = new CheckResult;
        $source = $snapshot->leadSource;
        if ($source === null) {
            return $result;
        }

        $subject = 'client';
        $connections = array_values(array_filter($snapshot->activeConnections(), fn ($c) => $snapshot->isFresh($c)));
        if (! $snapshot->isFresh($source) || ($connections === [] && $snapshot->activeConnections() !== [])) {
            return $result->keep($this->subjectKey($snapshot, $subject));
        }

        $hours = max(1, (int) $thresholds['hours']);
        $cutoff = $snapshot->now->subHours($hours);
        // Daily data: the complete days covering the window plus today so far.
        $from = $snapshot->daysAgo((int) ceil($hours / 24));
        $to = $snapshot->today()->toDateString();

        $spend = 0.0;
        $byPlatform = [];
        foreach ($connections as $connection) {
            $amount = $snapshot->sum($connection->id, $from, $to, 'spend');
            $spend += $amount;
            $byPlatform[$connection->platform->value] = ($byPlatform[$connection->platform->value] ?? 0) + $amount;
        }
        if ($spend < (float) $thresholds['min_spend']) {
            return $result;
        }

        $lastLead = $snapshot->lastLeadAt();
        $silentSince = $lastLead ?? $source->createdAt;
        if ($silentSince === null || $silentSince->greaterThan($cutoff)) {
            return $result;
        }

        $evidence = [
            'window' => $from.' – '.$to,
            'spend' => $this->money($snapshot, $spend),
            'last_lead_at' => $lastLead?->setTimezone($snapshot->now->getTimezone())->format('Y-m-d H:i') ?? '—',
            'lead_source' => $source->type,
        ];
        foreach ($byPlatform as $platform => $amount) {
            $evidence['spend_'.$platform] = $this->money($snapshot, $amount);
        }

        return $result->add($this->draft(
            $snapshot,
            $subject,
            Severity::Critical,
            'no_leads',
            ['hours' => $hours, 'spend' => $this->money($snapshot, $spend)],
            $evidence,
        ));
    }
}

<?php

namespace App\Domain\Checks\Checks;

use App\Domain\Checks\CheckResult;
use App\Domain\Checks\Snapshot\ClientSnapshot;
use App\Domain\Findings\Enums\Severity;
use App\Enums\Platform;
use App\Support\Format;

/**
 * Cost per lead over the last 7 days against the 28 days before, per
 * platform. Leads come from the lead source when it attributes enough of
 * them to the platform (Meta ids, gclid) — real leads beat platform
 * conversions — otherwise from the platform's own conversion count.
 * Zero recent leads is left to the conversion-drop check.
 */
class CplSpikeCheck extends BaseCheck
{
    public function key(): string
    {
        return 'cpl_spike';
    }

    public function fields(): array
    {
        return [
            'recent_days' => 'int',
            'baseline_days' => 'int',
            'warning_factor' => 'float',
            'critical_factor' => 'float',
            'min_baseline_leads' => 'float',
            'min_recent_spend' => 'float',
        ];
    }

    public function run(ClientSnapshot $snapshot, array $thresholds): CheckResult
    {
        $result = new CheckResult;
        $recent = max(1, (int) $thresholds['recent_days']);
        $baseline = max(1, (int) $thresholds['baseline_days']);
        $recentFrom = $snapshot->daysAgo($recent);
        $recentTo = $snapshot->daysAgo(1);
        $baseFrom = $snapshot->daysAgo($recent + $baseline);
        $baseTo = $snapshot->daysAgo($recent + 1);
        $minLeads = (float) $thresholds['min_baseline_leads'];

        foreach (Platform::cases() as $platform) {
            $connections = $snapshot->activeConnections($platform);
            if ($connections === []) {
                continue;
            }
            $subject = $platform->value;
            foreach ($connections as $connection) {
                if (! $snapshot->isFresh($connection)) {
                    $result->keep($this->subjectKey($snapshot, $subject));

                    continue 2;
                }
            }

            $spendRecent = $snapshot->sumPlatform($platform, $recentFrom, $recentTo, 'spend');
            $spendBase = $snapshot->sumPlatform($platform, $baseFrom, $baseTo, 'spend');
            if ($spendRecent < (float) $thresholds['min_recent_spend'] || $spendBase <= 0) {
                continue;
            }

            $basis = 'platform_conversions';
            $leadsRecent = $snapshot->sumPlatform($platform, $recentFrom, $recentTo, 'conversions');
            $leadsBase = $snapshot->sumPlatform($platform, $baseFrom, $baseTo, 'conversions');
            // A lead source that went silent is spend-without-leads' business;
            // counting its missing leads here would report the same outage twice.
            $lastLead = $snapshot->lastLeadAt();
            $sourceUsable = $snapshot->leadSource !== null && $snapshot->isFresh($snapshot->leadSource)
                && $lastLead !== null && $lastLead->greaterThan($snapshot->now->subHours(48));
            if ($sourceUsable) {
                $attributedBase = $snapshot->countLeads($baseFrom, $baseTo, $platform);
                if ($attributedBase >= $minLeads) {
                    $basis = 'lead_source';
                    $leadsBase = $attributedBase;
                    $leadsRecent = $snapshot->countLeads($recentFrom, $recentTo, $platform);
                }
            }

            if ($leadsBase < $minLeads || $leadsRecent <= 0) {
                continue;
            }

            $cplRecent = $spendRecent / $leadsRecent;
            $cplBase = $spendBase / $leadsBase;
            $factor = $cplRecent / $cplBase;
            $severity = match (true) {
                $factor >= (float) $thresholds['critical_factor'] => Severity::Critical,
                $factor >= (float) $thresholds['warning_factor'] => Severity::Warning,
                default => null,
            };
            if ($severity === null) {
                continue;
            }

            $result->add($this->draft($snapshot, $subject, $severity, 'spike',
                [
                    'platform' => ['type' => 'platform', 'value' => $platform->value],
                    'factor' => Format::x($factor),
                    'cpl' => $this->money($snapshot, $cplRecent),
                    'baseline' => $this->money($snapshot, $cplBase),
                ],
                [
                    'basis' => Format::t('evidence.basis_'.$basis),
                    'window' => $recentFrom.' – '.$recentTo,
                    'spend' => $this->money($snapshot, $spendRecent),
                    'leads' => Format::n($leadsRecent, 1),
                    'cpl' => $this->money($snapshot, $cplRecent),
                    'baseline' => $baseFrom.' – '.$baseTo,
                    'baseline_spend' => $this->money($snapshot, $spendBase),
                    'baseline_leads' => Format::n($leadsBase, 1),
                    'baseline_cpl' => $this->money($snapshot, $cplBase),
                    'factor' => Format::x($factor),
                ],
                $platform,
            ));
        }

        return $result;
    }
}

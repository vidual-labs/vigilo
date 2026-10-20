<?php

namespace App\Domain\Checks\Checks;

use App\Domain\Checks\CheckResult;
use App\Domain\Checks\Snapshot\ClientSnapshot;
use App\Domain\Findings\Enums\Severity;
use App\Support\Format;

/**
 * Conversions over the last 1 / 3 complete days at zero, or far below the
 * trailing baseline, while the account keeps spending. The classic symptom
 * of broken conversion tracking or a broken form.
 *
 * Guards against noise: the window needs real spend, the baseline enough
 * conversions, and the expected number of conversions in the window must be
 * high enough that "zero" is actually surprising.
 */
class ConversionDropCheck extends BaseCheck
{
    public function key(): string
    {
        return 'conversion_drop';
    }

    public function fields(): array
    {
        return [
            'windows_days' => 'int_list',
            'baseline_days' => 'int',
            'drop_ratio' => 'ratio',
            'min_window_spend' => 'float',
            'min_baseline_conversions' => 'float',
            'min_expected_conversions' => 'float',
        ];
    }

    public function run(ClientSnapshot $snapshot, array $thresholds): CheckResult
    {
        $result = new CheckResult;
        $baselineDays = max(1, (int) $thresholds['baseline_days']);

        foreach ($snapshot->activeConnections() as $connection) {
            $subject = 'conn:'.$connection->id;
            if (! $snapshot->isFresh($connection)) {
                $result->keep($this->subjectKey($snapshot, $subject));

                continue;
            }

            $worst = null;
            foreach ((array) $thresholds['windows_days'] as $window) {
                $window = max(1, (int) $window);
                $from = $snapshot->daysAgo($window);
                $to = $snapshot->daysAgo(1);
                $spend = $snapshot->sum($connection->id, $from, $to, 'spend');
                $conversions = $snapshot->sum($connection->id, $from, $to, 'conversions');
                $baseFrom = $snapshot->daysAgo($window + $baselineDays);
                $baseTo = $snapshot->daysAgo($window + 1);
                $baseline = $snapshot->sum($connection->id, $baseFrom, $baseTo, 'conversions');
                $expected = $baseline / $baselineDays * $window;

                if ($spend < (float) $thresholds['min_window_spend']
                    || $baseline < (float) $thresholds['min_baseline_conversions']
                    || $expected < (float) $thresholds['min_expected_conversions']) {
                    continue;
                }

                $ratio = $conversions / $expected;
                $severity = match (true) {
                    $conversions <= 0 => Severity::Critical,
                    $ratio < (float) $thresholds['drop_ratio'] => Severity::Warning,
                    default => null,
                };
                if ($severity === null) {
                    continue;
                }

                $candidate = compact('severity', 'window', 'from', 'to', 'spend', 'conversions', 'baseline', 'baseFrom', 'baseTo', 'expected', 'ratio');
                if ($worst === null || $severity->isWorseThan($worst['severity'])
                    || ($severity === $worst['severity'] && $ratio < $worst['ratio'])) {
                    $worst = $candidate;
                }
            }

            if ($worst === null) {
                continue;
            }

            $result->add($this->draft(
                $snapshot,
                $subject,
                $worst['severity'],
                $worst['conversions'] <= 0 ? 'zero' : 'drop',
                [
                    'account' => $connection->name,
                    'days' => $worst['window'],
                    'spend' => $this->money($snapshot, $worst['spend']),
                    'ratio' => Format::p($worst['ratio']),
                ],
                [
                    'account' => $connection->name,
                    'window' => $worst['from'].' – '.$worst['to'],
                    'spend' => $this->money($snapshot, $worst['spend']),
                    'conversions' => Format::n($worst['conversions'], 1),
                    'expected_conversions' => Format::n($worst['expected'], 1),
                    'ratio' => Format::p($worst['ratio']),
                    'baseline' => $worst['baseFrom'].' – '.$worst['baseTo'],
                    'baseline_conversions' => Format::n($worst['baseline'], 1),
                ],
                $connection->platform,
            ));
        }

        return $result;
    }
}

<?php

namespace App\Domain\Checks\Checks;

use App\Domain\Checks\CheckResult;
use App\Domain\Checks\Snapshot\ClientSnapshot;
use App\Domain\Findings\Enums\Severity;
use App\Enums\Platform;
use App\Support\Format;

/**
 * Month-to-date spend against the pro-rata share of the monthly budget,
 * per platform. Uses complete days only (today is still running).
 */
class BudgetPacingCheck extends BaseCheck
{
    public function key(): string
    {
        return 'budget_pacing';
    }

    public function fields(): array
    {
        return ['tolerance' => 'ratio', 'critical_overspend' => 'ratio', 'min_day_of_month' => 'int'];
    }

    public function run(ClientSnapshot $snapshot, array $thresholds): CheckResult
    {
        $result = new CheckResult;
        $today = $snapshot->today();
        $elapsed = $today->day - 1;
        $daysInMonth = $today->daysInMonth;

        foreach ($snapshot->budgets as $platformKey => $budget) {
            $platform = Platform::tryFrom($platformKey);
            $connections = $platform ? $snapshot->activeConnections($platform) : [];
            if ($platform === null || $budget <= 0 || $connections === []) {
                continue;
            }

            $subject = $platform->value;
            foreach ($connections as $connection) {
                if (! $snapshot->isFresh($connection)) {
                    $result->keep($this->subjectKey($snapshot, $subject));

                    continue 2;
                }
            }
            if ($elapsed < 1) {
                continue;
            }

            $mtd = $snapshot->sumPlatform($platform, $today->startOfMonth()->toDateString(), $snapshot->daysAgo(1), 'spend');
            $expected = $budget * $elapsed / $daysInMonth;
            $projected = $mtd / $elapsed * $daysInMonth;
            $pace = $expected > 0 ? $mtd / $expected : 0.0;

            [$severity, $title] = match (true) {
                $mtd >= $budget => [Severity::Critical, 'over_budget'],
                $today->day < (int) $thresholds['min_day_of_month'] => [null, null],
                $projected >= $budget * (1 + (float) $thresholds['critical_overspend']) => [Severity::Critical, 'overpacing'],
                $pace > 1 + (float) $thresholds['tolerance'] => [Severity::Warning, 'overpacing'],
                $pace < 1 - (float) $thresholds['tolerance'] => [Severity::Warning, 'underpacing'],
                default => [null, null],
            };
            if ($severity === null) {
                continue;
            }

            $result->add($this->draft($snapshot, $subject, $severity, $title,
                [
                    'platform' => ['type' => 'platform', 'value' => $platform->value],
                    'spend' => $this->money($snapshot, $mtd),
                    'budget' => $this->money($snapshot, $budget),
                    'pace' => Format::p($pace),
                    'projected' => $this->money($snapshot, $projected),
                ],
                [
                    'month_to_date' => $this->money($snapshot, $mtd),
                    'expected_to_date' => $this->money($snapshot, $expected),
                    'monthly_budget' => $this->money($snapshot, $budget),
                    'pace' => Format::p($pace),
                    'projected_month_end' => $this->money($snapshot, $projected),
                    'days_elapsed' => $elapsed.' / '.$daysInMonth,
                ],
                $platform,
            ));
        }

        return $result;
    }
}

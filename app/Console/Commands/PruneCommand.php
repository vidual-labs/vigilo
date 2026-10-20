<?php

namespace App\Console\Commands;

use App\Domain\Findings\Enums\FindingStatus;
use App\Models\AdMetricDaily;
use App\Models\FetchLog;
use App\Models\Finding;
use App\Models\Lead;
use App\Models\Run;
use Illuminate\Console\Command;

/** Retention: deletes data older than the VIGILO_RETENTION_* windows. */
class PruneCommand extends Command
{
    protected $signature = 'vigilo:prune';

    protected $description = 'Delete fetch logs, metrics, lead metadata and resolved findings past their retention';

    public function handle(): int
    {
        $r = (array) config('vigilo.retention');
        $deleted = [
            'fetch_logs' => FetchLog::query()->where('started_at', '<', now()->subDays((int) $r['fetch_logs_days']))->delete(),
            'metrics' => AdMetricDaily::query()->where('date', '<', now()->subDays((int) $r['metrics_days'])->toDateString())->delete(),
            'leads' => Lead::query()->where('submitted_at', '<', now()->subDays((int) $r['leads_days']))->delete(),
            'findings' => Finding::query()->where('status', FindingStatus::Resolved->value)
                ->where('resolved_at', '<', now()->subDays((int) $r['resolved_findings_days']))->delete(),
            'runs' => Run::query()->where('started_at', '<', now()->subDays((int) $r['runs_days']))->delete(),
        ];

        $this->info('vigilo: pruned '.json_encode($deleted));

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Docker healthcheck for the queue container: unhealthy when a job has been
 * waiting far longer than the worker's poll interval (worker stuck or dead).
 */
class QueueHealthCommand extends Command
{
    protected $signature = 'vigilo:health:queue {--max-wait=900 : seconds a ready job may wait}';

    protected $description = 'Exit non-zero when queued jobs are not being picked up';

    public function handle(): int
    {
        $oldest = DB::table('jobs')->whereNull('reserved_at')->where('available_at', '<=', now()->timestamp)->min('available_at');
        if ($oldest !== null && now()->timestamp - (int) $oldest > (int) $this->option('max-wait')) {
            $this->error('queue: oldest ready job has waited '.(now()->timestamp - (int) $oldest).'s');

            return self::FAILURE;
        }

        $this->line('queue: ok');

        return self::SUCCESS;
    }
}

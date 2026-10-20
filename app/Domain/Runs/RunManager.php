<?php

namespace App\Domain\Runs;

use App\Domain\Runs\Enums\RunStatus;
use App\Domain\Runs\Enums\RunTrigger;
use App\Jobs\RunClientChecks;
use App\Jobs\SendDigests;
use App\Jobs\SyncClientData;
use App\Models\Client;
use App\Models\FetchLog;
use App\Models\Finding;
use App\Models\Run;
use App\Models\User;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

/**
 * A run = for each client: fetch (ads, leads, landing pages) → checks;
 * once every client is done → digests (scheduled runs only). Clients run as
 * one job chain each inside a batch, so one broken client never blocks the
 * others.
 */
class RunManager
{
    public function start(RunTrigger $trigger, ?Client $client = null, ?User $user = null, ?bool $sendDigest = null, bool $fetch = true): Run
    {
        $run = Run::query()->create([
            'trigger' => $trigger,
            'client_id' => $client?->id,
            'user_id' => $user?->id,
            'status' => RunStatus::Running,
            'send_digest' => $sendDigest ?? ($trigger === RunTrigger::Schedule && $client === null),
            'started_at' => now(),
        ]);

        $clients = $client ? collect([$client]) : Client::query()->active()
            // Demo clients only make sense against the simulators.
            ->when(! config('vigilo.mock_mode'), fn ($q) => $q->where('is_demo', false))
            ->orderBy('id')->get();
        $runId = $run->id;
        $jobs = $clients->map(fn (Client $c) => array_values(array_filter([
            $fetch ? new SyncClientData($c->id) : null,
            new RunClientChecks($c->id, $runId),
        ])))->all();

        if ($jobs === []) {
            $this->finish($runId, 0);

            return $run->refresh();
        }

        $batch = Bus::batch($jobs)
            ->name('vigilo run #'.$runId)
            ->allowFailures()
            ->finally(function (Batch $batch) use ($runId) {
                app(RunManager::class)->finish($runId, $batch->failedJobs);
            })
            ->dispatch();

        Run::query()->whereKey($runId)->whereNull('batch_id')->update(['batch_id' => $batch->id]);

        return $run->refresh();
    }

    public function finish(int $runId, int $failedJobs): void
    {
        $run = Run::query()->find($runId);
        if ($run === null || $run->status !== RunStatus::Running) {
            return;
        }

        $since = $run->started_at;
        $stats = [
            'clients' => $run->client_id ? 1 : Client::query()->active()->count(),
            'opened' => Finding::query()->where('opened_at', '>=', $since)->count(),
            'resolved' => Finding::query()->where('resolved_at', '>=', $since)->count(),
            'fetch_failures' => FetchLog::query()->where('started_at', '>=', $since)->where('status', 'failed')->count(),
            'failed_jobs' => $failedJobs,
        ];

        $run->forceFill([
            'status' => $failedJobs > 0 ? RunStatus::CompletedWithErrors : RunStatus::Completed,
            'stats' => $stats,
            'finished_at' => now(),
        ])->save();

        if ($run->send_digest) {
            SendDigests::dispatch($run->id);
        }
    }
}

<?php

namespace App\Jobs;

use App\Domain\Checks\Services\CheckRunner;
use App\Domain\Notifications\Notifier;
use App\Domain\Runs\Enums\RunTrigger;
use App\Models\Client;
use App\Models\Run;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunClientChecks implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    /** @param int|null $runId null = ad-hoc ("Fetch now" on a connection) */
    public function __construct(public int $clientId, public ?int $runId = null) {}

    public function handle(CheckRunner $runner, Notifier $notifier): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        $client = Client::query()->find($this->clientId);
        if ($client === null) {
            return;
        }

        $summary = $runner->run($client);

        // In the scheduled run the digest follows minutes later and carries
        // the news; anywhere else critical findings are mailed right away.
        $trigger = $this->runId ? Run::query()->whereKey($this->runId)->value('trigger') : null;
        $scheduled = $trigger === RunTrigger::Schedule->value || $trigger === RunTrigger::Schedule;
        $notifier->critical([...$summary['opened'], ...$summary['escalated']], mail: ! $scheduled);
    }
}

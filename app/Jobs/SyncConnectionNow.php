<?php

namespace App\Jobs;

use App\Domain\AdPlatforms\Services\AdDataSync;
use App\Models\AdConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** "Fetch now" on the connections page; chained with RunClientChecks. */
class SyncConnectionNow implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $connectionId) {}

    public function handle(AdDataSync $sync): void
    {
        $connection = AdConnection::query()->find($this->connectionId);
        if ($connection !== null) {
            $sync->sync($connection);
        }
    }
}

<?php

namespace App\Jobs;

use App\Domain\Leads\Services\LeadSync;
use App\Models\LeadSource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncLeadSourceNow implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $leadSourceId) {}

    public function handle(LeadSync $sync): void
    {
        $source = LeadSource::query()->find($this->leadSourceId);
        if ($source !== null) {
            $sync->sync($source);
        }
    }
}

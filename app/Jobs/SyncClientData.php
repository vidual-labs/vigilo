<?php

namespace App\Jobs;

use App\Domain\AdPlatforms\Services\AdDataSync;
use App\Domain\Checks\LandingPages\LandingPageProbeService;
use App\Domain\Leads\Services\LeadSync;
use App\Models\Client;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches everything for one client. Never throws: a failing connection is
 * recorded on the connection (and becomes a connection-health finding);
 * the checks that follow in the chain must still run.
 */
class SyncClientData implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public int $clientId) {}

    public function handle(AdDataSync $ads, LeadSync $leads, LandingPageProbeService $probes): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        $client = Client::query()->with(['connections', 'leadSource'])->find($this->clientId);
        if ($client === null || ! $client->is_active) {
            return;
        }

        foreach ($client->connections->where('is_active', true) as $connection) {
            $this->safely(fn () => $ads->sync($connection), "ad connection {$connection->id}");
        }

        if ($client->leadSource && $client->leadSource->is_active) {
            $this->safely(fn () => $leads->sync($client->leadSource), "lead source {$client->leadSource->id}");
        }

        $this->safely(fn () => $probes->probeClient($client), "landing pages of client {$client->id}");
    }

    private function safely(\Closure $step, string $what): void
    {
        try {
            $step();
        } catch (Throwable $e) {
            Log::error("vigilo: fetching {$what} failed: ".$e->getMessage(), ['exception' => $e]);
        }
    }
}

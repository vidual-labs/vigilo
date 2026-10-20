<?php

namespace App\Domain\Checks\Services;

use App\Domain\Checks\CheckRegistry;
use App\Domain\Checks\CheckResult;
use App\Domain\Checks\Snapshot\SnapshotFactory;
use App\Domain\Findings\Services\FindingReconciler;
use App\Models\Client;
use App\Models\Finding;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Runs every registered check for one client and reconciles the findings. */
class CheckRunner
{
    public function __construct(
        private readonly CheckRegistry $registry,
        private readonly SnapshotFactory $snapshots,
        private readonly ThresholdResolver $thresholds,
        private readonly FindingReconciler $reconciler,
    ) {}

    /**
     * @return array{opened: list<Finding>, escalated: list<Finding>, resolved: list<Finding>, errors: list<string>}
     */
    public function run(Client $client, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $client->load(['connections', 'leadSource', 'budgets', 'checkSettings']);
        $snapshot = $this->snapshots->build($client, $now);
        $this->thresholds->flush();

        $summary = ['opened' => [], 'escalated' => [], 'resolved' => [], 'errors' => []];

        foreach ($this->registry->all() as $key => $check) {
            $settings = $this->thresholds->resolve($check, $client);

            if (! $client->is_active || ! $settings['enabled']) {
                // A disabled check (or archived client) has nothing open.
                $result = new CheckResult;
            } else {
                try {
                    $result = $check->run($snapshot, $settings['values']);
                } catch (Throwable $e) {
                    // A broken check must not resolve its findings or stop the others.
                    Log::error("vigilo: check {$key} failed for client {$client->id}", ['exception' => $e]);
                    $summary['errors'][] = $key.': '.$e->getMessage();

                    continue;
                }
            }

            $changes = $this->reconciler->reconcile($client, $key, $result, $now);
            foreach (['opened', 'escalated', 'resolved'] as $type) {
                array_push($summary[$type], ...$changes[$type]);
            }
        }

        return $summary;
    }
}

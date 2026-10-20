<?php

namespace App\Domain\Checks\Snapshot;

use App\Enums\Platform;
use Carbon\CarbonImmutable;

/**
 * Everything the checks know about one client at one moment, as plain
 * data. Built from the database by SnapshotFactory, and by hand (or from
 * JSON fixtures) in tests.
 */
final readonly class ClientSnapshot
{
    /**
     * @param  list<ConnectionState>  $connections
     * @param  array<int, array<string, array{spend: float, conversions: float}>>  $metrics  connection id => date => totals
     * @param  list<AdState>  $ads
     * @param  list<LeadFact>  $leads
     * @param  array<string, float>  $budgets  platform => monthly amount
     * @param  list<ProbeState>  $probes
     */
    public function __construct(
        public int $clientId,
        public string $clientName,
        public string $currency,
        /** Now, in the client's timezone. */
        public CarbonImmutable $now,
        public array $connections = [],
        public array $metrics = [],
        public array $ads = [],
        public ?LeadSourceState $leadSource = null,
        public array $leads = [],
        public array $budgets = [],
        public array $probes = [],
    ) {}

    public function today(): CarbonImmutable
    {
        return $this->now->startOfDay();
    }

    /** Date string N days before today (1 = yesterday). */
    public function daysAgo(int $days): string
    {
        return $this->today()->subDays($days)->toDateString();
    }

    /**
     * Data counts as fresh when the connection's last fully successful fetch
     * happened today (client time). Otherwise "no conversions" might just
     * mean "no data", and checks must not conclude anything.
     */
    public function isFresh(ConnectionState|LeadSourceState $state): bool
    {
        return $state->lastSuccessAt !== null
            && $state->lastSuccessAt->greaterThanOrEqualTo($this->today());
    }

    /** @return list<ConnectionState> */
    public function activeConnections(?Platform $platform = null): array
    {
        return array_values(array_filter(
            $this->connections,
            fn (ConnectionState $c) => $c->isActive && ($platform === null || $c->platform === $platform),
        ));
    }

    /** @param 'spend'|'conversions' $metric */
    public function sum(int $connectionId, string $from, string $to, string $metric): float
    {
        $total = 0.0;
        foreach ($this->metrics[$connectionId] ?? [] as $date => $row) {
            if ($date >= $from && $date <= $to) {
                $total += (float) $row[$metric];
            }
        }

        return $total;
    }

    /** @param 'spend'|'conversions' $metric */
    public function sumPlatform(Platform $platform, string $from, string $to, string $metric): float
    {
        $total = 0.0;
        foreach ($this->activeConnections($platform) as $connection) {
            $total += $this->sum($connection->id, $from, $to, $metric);
        }

        return $total;
    }

    /** Countable leads for a platform (null = any) submitted within the date range (client time). */
    public function countLeads(string $from, string $to, ?Platform $platform = null, bool $countableOnly = true): int
    {
        $count = 0;
        foreach ($this->leads as $lead) {
            if ($countableOnly && ! $lead->countable) {
                continue;
            }
            if ($platform !== null && $lead->platform !== $platform) {
                continue;
            }
            $date = $lead->submittedAt->setTimezone($this->now->getTimezone())->toDateString();
            if ($date >= $from && $date <= $to) {
                $count++;
            }
        }

        return $count;
    }

    public function lastLeadAt(): ?CarbonImmutable
    {
        $last = null;
        foreach ($this->leads as $lead) {
            if ($last === null || $lead->submittedAt->greaterThan($last)) {
                $last = $lead->submittedAt;
            }
        }

        return $last;
    }

    public function connection(int $id): ?ConnectionState
    {
        foreach ($this->connections as $connection) {
            if ($connection->id === $id) {
                return $connection;
            }
        }

        return null;
    }
}

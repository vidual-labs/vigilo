<?php

namespace Tests\Support;

use App\Domain\AdPlatforms\Enums\ConnectionStatus;
use App\Domain\AdPlatforms\Enums\ReviewStatus;
use App\Domain\Checks\Snapshot\AdState;
use App\Domain\Checks\Snapshot\ClientSnapshot;
use App\Domain\Checks\Snapshot\ConnectionState;
use App\Domain\Checks\Snapshot\LeadFact;
use App\Domain\Checks\Snapshot\LeadSourceState;
use App\Domain\Checks\Snapshot\ProbeState;
use App\Enums\Platform;
use Carbon\CarbonImmutable;

/**
 * Builds ClientSnapshots for check unit tests, either fluently or from a
 * JSON fixture in tests/Fixtures/snapshots. Dates are relative to "now" so
 * fixtures never go stale:
 *
 *   {"now": "2026-10-20 08:00", "connections": [{"id": 1, "platform": "google"}],
 *    "series": [{"connection": 1, "from": 30, "to": 4, "spend": 100, "conversions": 4}], …}
 *
 * `from`/`to` are "days ago" (1 = yesterday), both inclusive.
 */
final class SnapshotBuilder
{
    private array $connections = [];

    private array $metrics = [];

    private array $ads = [];

    private ?LeadSourceState $leadSource = null;

    private array $leads = [];

    private array $budgets = [];

    private array $probes = [];

    public function __construct(public readonly CarbonImmutable $now, private string $currency = 'EUR') {}

    public static function at(string $now = '2026-10-20 08:00', string $tz = 'Europe/Berlin'): self
    {
        return new self(CarbonImmutable::parse($now, $tz));
    }

    public static function fromFixture(string $name): self
    {
        $data = fixture('snapshots/'.$name.'.json');
        $builder = self::at($data['now'] ?? '2026-10-20 08:00');
        foreach ($data['connections'] ?? [] as $c) {
            $builder->connection($c['id'], Platform::from($c['platform']), $c);
        }
        foreach ($data['series'] ?? [] as $s) {
            $builder->series($s['connection'], $s['from'], $s['to'], (float) $s['spend'], (float) $s['conversions']);
        }
        foreach ($data['budgets'] ?? [] as $platform => $amount) {
            $builder->budget(Platform::from($platform), (float) $amount);
        }
        if (isset($data['lead_source'])) {
            $builder->leadSource($data['lead_source']);
        }
        foreach ($data['leads'] ?? [] as $l) {
            $builder->leadsDaily($l['from'], $l['to'], $l['per_day'], isset($l['platform']) ? Platform::from($l['platform']) : null);
        }

        return $builder;
    }

    /** @param array<string, mixed> $overrides */
    public function connection(int $id, Platform $platform = Platform::Google, array $overrides = []): self
    {
        $this->connections[$id] = new ConnectionState(
            id: $id,
            platform: $platform,
            name: $overrides['name'] ?? $platform->label().' '.$id,
            isActive: $overrides['is_active'] ?? true,
            status: isset($overrides['status']) ? ConnectionStatus::from($overrides['status']) : ConnectionStatus::Ok,
            lastSuccessAt: array_key_exists('last_success_hours_ago', $overrides)
                ? ($overrides['last_success_hours_ago'] === null ? null : $this->now->subHours($overrides['last_success_hours_ago']))
                : $this->now->subMinutes(5),
            lastAttemptAt: $this->now->subMinutes(5),
            lastErrorCode: $overrides['last_error_code'] ?? null,
            lastError: $overrides['last_error'] ?? null,
            tokenExpiresAt: isset($overrides['token_expires_in_days']) ? $this->now->addDays($overrides['token_expires_in_days']) : null,
            accountActive: $overrides['account_active'] ?? true,
            accountStatus: $overrides['account_status'] ?? null,
            createdAt: isset($overrides['created_hours_ago']) ? $this->now->subHours($overrides['created_hours_ago']) : $this->now->subDays(90),
        );

        return $this;
    }

    /** Same spend/conversions every day from $from to $to days ago (inclusive). */
    public function series(int $connectionId, int $from, int $to, float $spend, float $conversions): self
    {
        for ($ago = $from; $ago >= $to; $ago--) {
            $date = $this->now->startOfDay()->subDays($ago)->toDateString();
            $this->metrics[$connectionId][$date] = ['spend' => $spend, 'conversions' => $conversions];
        }

        return $this;
    }

    /** Spend/conversions for each day of the current month up to yesterday. */
    public function monthToDate(int $connectionId, float $spendPerDay, float $conversions = 3): self
    {
        $days = $this->now->day - 1;

        return $days > 0 ? $this->series($connectionId, $days, 1, $spendPerDay, $conversions) : $this;
    }

    public function budget(Platform $platform, float $amount): self
    {
        $this->budgets[$platform->value] = $amount;

        return $this;
    }

    /** @param array<string, mixed> $overrides */
    public function leadSource(array $overrides = []): self
    {
        $this->leadSource = new LeadSourceState(
            id: $overrides['id'] ?? 1,
            type: $overrides['type'] ?? 'lodgely',
            status: isset($overrides['status']) ? ConnectionStatus::from($overrides['status']) : ConnectionStatus::Ok,
            lastSuccessAt: array_key_exists('last_success_hours_ago', $overrides)
                ? ($overrides['last_success_hours_ago'] === null ? null : $this->now->subHours($overrides['last_success_hours_ago']))
                : $this->now->subMinutes(5),
            lastAttemptAt: $this->now->subMinutes(5),
            lastErrorCode: $overrides['last_error_code'] ?? null,
            lastError: $overrides['last_error'] ?? null,
            createdAt: isset($overrides['created_hours_ago']) ? $this->now->subHours($overrides['created_hours_ago']) : $this->now->subDays(90),
        );

        return $this;
    }

    public function lead(CarbonImmutable $at, ?Platform $platform = null, bool $countable = true): self
    {
        $this->leads[] = new LeadFact($at->utc(), $countable, $platform);

        return $this;
    }

    /** $perDay leads at noon on each day $from..$to days ago. */
    public function leadsDaily(int $from, int $to, int $perDay, ?Platform $platform = null): self
    {
        for ($ago = $from; $ago >= $to; $ago--) {
            for ($n = 0; $n < $perDay; $n++) {
                $this->lead($this->now->startOfDay()->subDays($ago)->setTime(12, $n % 60), $platform);
            }
        }

        return $this;
    }

    /** @param list<string> $topics */
    public function ad(int $connectionId, string $id, string $campaignId, ReviewStatus $review, bool $active = true, array $topics = []): self
    {
        $this->ads[] = new AdState($connectionId, $id, 'Ad '.$id, $campaignId, 'Campaign '.$campaignId, $active, $review, strtoupper($review->value), $topics);

        return $this;
    }

    /** @param array<string, mixed> $p */
    public function probe(string $url, array $p = []): self
    {
        $this->probes[] = new ProbeState(
            url: $url,
            urlHash: sha1($url),
            origin: $p['origin'] ?? 'ad',
            finalUrl: $p['final_url'] ?? $url,
            statusCode: array_key_exists('status', $p) ? $p['status'] : 200,
            redirectChain: $p['chain'] ?? [['url' => $url, 'status' => $p['status'] ?? 200]],
            redirectCount: $p['redirects'] ?? 0,
            responseMs: $p['ms'] ?? 300,
            tlsExpiresAt: isset($p['tls_days']) ? $this->now->addDays($p['tls_days']) : null,
            error: $p['error'] ?? null,
            consecutiveFailures: $p['failures'] ?? 0,
            checkedAt: isset($p['checked_hours_ago']) ? $this->now->subHours($p['checked_hours_ago']) : $this->now->subMinutes(2),
        );

        return $this;
    }

    public function build(): ClientSnapshot
    {
        return new ClientSnapshot(
            clientId: 7,
            clientName: 'Test Client',
            currency: $this->currency,
            now: $this->now,
            connections: array_values($this->connections),
            metrics: $this->metrics,
            ads: $this->ads,
            leadSource: $this->leadSource,
            leads: $this->leads,
            budgets: $this->budgets,
            probes: $this->probes,
        );
    }
}

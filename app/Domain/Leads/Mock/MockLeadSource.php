<?php

namespace App\Domain\Leads\Mock;

use App\Domain\AdPlatforms\Exceptions\AdPlatformException;
use App\Domain\AdPlatforms\Mock\MockAdPlatform;
use App\Domain\AdPlatforms\Mock\MockGoogleAdsPlatform;
use App\Domain\AdPlatforms\Mock\MockMetaAdsPlatform;
use App\Domain\AdPlatforms\Mock\Seeded;
use App\Domain\Leads\Contracts\LeadSource;
use App\Domain\Leads\Data\LeadMetadata;
use App\Domain\Leads\Data\LeadPage;
use App\Domain\Leads\Enums\LeadStatus;
use App\Enums\Platform;
use App\Models\AdConnection;
use App\Models\LeadSource as LeadSourceModel;
use Carbon\CarbonImmutable;

/**
 * Simulated lead feed that agrees with the simulated ad accounts: each
 * platform day produces as many leads as the mock reported conversions —
 * except under `tracking_broken`, where leads keep arriving while the
 * platform sees none (that mismatch is the point of the scenario).
 * Lead source scenarios: healthy, sync_broken (nothing for 3 days), auth_failed.
 */
class MockLeadSource implements LeadSource
{
    private int $attempts = 0;

    public function fetch(LeadSourceModel $source, ?string $cursor): LeadPage
    {
        $this->attempt($source);

        $client = $source->client;
        $tz = $client->timezone ?: config('vigilo.timezone');
        $now = CarbonImmutable::now($tz);
        $since = $cursor
            ? CarbonImmutable::parse($cursor)->setTimezone($tz)
            : $now->subDays((int) config('vigilo.lodgely.initial_lookback_days', 60))->startOfDay();
        $blackoutFrom = $source->mockScenario() === 'sync_broken' ? $now->subDays(3)->startOfDay() : null;

        $leads = [];
        foreach ($client->connections()->where('is_active', true)->get() as $connection) {
            foreach ($this->dailyLeadCounts($connection, $since->startOfDay(), $now) as $date => $count) {
                for ($n = 0; $n < $count; $n++) {
                    $lead = $this->makeLead($connection, $date, $n, $tz, $now);
                    if ($lead->submittedAt->greaterThan($since->utc()) && $lead->submittedAt->lessThanOrEqualTo($now->utc())
                        && ! ($blackoutFrom && $lead->submittedAt->greaterThanOrEqualTo($blackoutFrom->utc()))) {
                        $leads[] = $lead;
                    }
                }
            }
        }

        return new LeadPage($leads, $now->utc()->toIso8601String(), false);
    }

    public function test(LeadSourceModel $source): string
    {
        $this->attempt($source);

        return __('Simulated lead source is reachable.');
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function resetAttempts(): void
    {
        $this->attempts = 0;
    }

    /** @return array<string, int> date => leads */
    private function dailyLeadCounts(AdConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $simulated = clone $connection;
        if ($connection->mockScenario() === 'tracking_broken' || $connection->mockScenario() === 'auth_failed') {
            $simulated->settings = array_merge($connection->settings ?? [], ['mock_scenario' => 'healthy']);
        }

        /** @var MockAdPlatform $mock */
        $mock = app($connection->platform === Platform::Google ? MockGoogleAdsPlatform::class : MockMetaAdsPlatform::class);

        $counts = [];
        foreach ($mock->dailyMetrics($simulated, $from, $to) as $day) {
            $counts[$day->date] = ($counts[$day->date] ?? 0) + (int) round($day->conversions * 0.9);
        }

        return $counts;
    }

    private function makeLead(AdConnection $connection, string $date, int $n, string $tz, CarbonImmutable $now): LeadMetadata
    {
        $seed = [$connection->id.'', $date, (string) $n];
        $submitted = CarbonImmutable::parse($date, $tz)->addSeconds((int) (7 * 3600 + Seeded::float('t', ...$seed) * 14 * 3600));
        $age = (int) $submitted->diffInDays($now);
        $roll = Seeded::float('status', ...$seed);

        $status = match (true) {
            $roll < 0.05 => LeadStatus::Duplicate,
            $age < 2 => LeadStatus::New,
            $age < 10 => $roll < 0.5 ? LeadStatus::Reviewed : LeadStatus::Pending,
            $roll < 0.17 => LeadStatus::Successful,
            $roll < 0.5 => LeadStatus::Declined,
            $roll < 0.6 => LeadStatus::NoReply,
            default => LeadStatus::OfferSent,
        };

        $isMeta = $connection->platform === Platform::Meta;

        return new LeadMetadata(
            externalId: sprintf('mock-%d-%s-%d', $connection->id, $date, $n),
            submittedAt: $submitted->utc(),
            status: $status,
            sourceType: $isMeta ? 'meta_lead_ads' : 'openflow',
            metaCampaignId: $isMeta ? '23850110' : null,
            metaAdsetId: $isMeta ? '238501109' : null,
            metaAdId: $isMeta ? '2385011001' : null,
            metaFormId: $isMeta ? '9001' : null,
            hasGclid: ! $isMeta,
            flags: ['duplicate' => $status === LeadStatus::Duplicate, 'qualified' => $status === LeadStatus::Successful],
        );
    }

    /** @throws AdPlatformException */
    private function attempt(LeadSourceModel $source): void
    {
        $this->attempts++;
        if ($source->mockScenario() === 'auth_failed') {
            throw new AdPlatformException('Simulated: lodgely rejected the API token (401).', AdPlatformException::AUTH, 401);
        }
    }
}

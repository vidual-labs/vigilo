<?php

namespace App\Domain\AdPlatforms\Mock;

use App\Domain\AdPlatforms\Contracts\AdPlatform;
use App\Domain\AdPlatforms\Data\AccountInfo;
use App\Domain\AdPlatforms\Data\AdSnapshot;
use App\Domain\AdPlatforms\Data\CampaignDay;
use App\Domain\AdPlatforms\Data\TokenInfo;
use App\Domain\AdPlatforms\Enums\ReviewStatus;
use App\Domain\AdPlatforms\Exceptions\AdPlatformException;
use App\Models\AdConnection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Deterministic simulator shared by the Google and Meta mocks. Numbers are a
 * function of (connection, campaign, date), so re-fetching never changes
 * history, and each scenario bends the data in exactly one way the
 * matching check should notice.
 */
abstract class MockAdPlatform implements AdPlatform
{
    private int $attempts = 0;

    /**
     * @return list<array{id: string, name: string, share: float, path: string}>
     */
    abstract protected function campaigns(): array;

    /** Typical cost per conversion on this platform. */
    abstract protected function baseCpl(): float;

    /** Typical cost per click on this platform. */
    abstract protected function baseCpc(): float;

    abstract protected function accountName(AdConnection $connection): string;

    public function account(AdConnection $connection): AccountInfo
    {
        $this->attempt($connection);
        $disabled = $connection->mockScenario() === 'account_disabled';

        return new AccountInfo(
            name: $this->accountName($connection),
            currency: $connection->client->currency ?? 'EUR',
            timezone: $connection->client->timezone ?? 'Europe/Berlin',
            isActive: ! $disabled,
            rawStatus: $disabled ? $this->disabledStatus() : $this->activeStatus(),
        );
    }

    public function dailyMetrics(AdConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $this->attempt($connection);

        $scenario = $connection->mockScenario();
        $client = $connection->client;
        $tz = $client->timezone ?? 'Europe/Berlin';
        $today = CarbonImmutable::now($tz)->startOfDay();
        $monthly = $client->budgetFor($this->platform()) ?? 3000.0;

        $days = [];
        for ($date = $from->startOfDay(); $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            if ($date->greaterThan($today)) {
                break;
            }
            $ageDays = (int) $date->diffInDays($today);
            // Today is still running: only the elapsed share of the day.
            $dayShare = $ageDays === 0 ? max(0.05, CarbonImmutable::now($tz)->secondsSinceMidnight() / 86400) : 1.0;
            $dailyBudget = $monthly / $date->daysInMonth;

            foreach ($this->campaigns() as $campaign) {
                $key = [$connection->id.'', $campaign['id'], $date->toDateString()];
                $spend = $dailyBudget * $campaign['share'] * Seeded::jitter(0.12, 'spend', ...$key);
                $cpl = $this->baseCpl() * Seeded::jitter(0.15, 'cpl', ...$key);

                [$spendFactor, $conversionFactor] = $this->factors($scenario, $ageDays, $date->isSameMonth($today));

                $spend = round($spend * $spendFactor * $dayShare, 2);
                $conversions = $spend / $cpl * $conversionFactor;
                // Healthy days get at least one conversion so random zero days
                // never trigger alerts; only the scenario should.
                $conversions = match (true) {
                    $conversionFactor === 1.0 => (float) max($ageDays === 0 ? 0 : 1, round($conversions)),
                    default => round($conversions, 1),
                };
                $clicks = (int) round($spend / ($this->baseCpc() * Seeded::jitter(0.2, 'cpc', ...$key)));

                $days[] = new CampaignDay(
                    date: $date->toDateString(),
                    campaignId: $campaign['id'],
                    campaignName: $campaign['name'],
                    spend: $spend,
                    impressions: $clicks * (int) round(18 + 20 * Seeded::float('ctr', ...$key)),
                    clicks: $clicks,
                    conversions: $conversions,
                );
            }
        }

        return $days;
    }

    /**
     * Spend and conversion multipliers a scenario applies to one day.
     *
     * @return array{0: float, 1: float}
     */
    private function factors(string $scenario, int $ageDays, bool $inCurrentMonth): array
    {
        return match ($scenario) {
            'overspend' => [$inCurrentMonth ? 1.55 : 1.0, 1.0],
            'underspend' => [$inCurrentMonth ? 0.5 : 1.0, 1.0],
            'tracking_broken' => [1.0, $ageDays <= 2 ? 0.0 : 1.0],
            'conversion_drop' => [1.0, $ageDays <= 3 ? 0.3 : 1.0],
            'cpl_spike' => $ageDays <= 7 ? [1.1, 0.6] : [1.0, 1.0],
            default => [1.0, 1.0],
        };
    }

    public function ads(AdConnection $connection): array
    {
        $this->attempt($connection);

        $scenario = $connection->mockScenario();
        $domain = 'https://www.'.Str::slug($connection->client->name ?? 'client').'.example';
        $ads = [];

        foreach ($this->campaigns() as $c => $campaign) {
            foreach (['A', 'B'] as $i => $variant) {
                $review = ReviewStatus::Approved;
                $raw = $this->approvedStatus();
                $topics = [];
                $path = $campaign['path'];

                if ($scenario === 'disapproved_ads' && $c === 0) {
                    [$review, $raw, $topics] = [ReviewStatus::Disapproved, 'DISAPPROVED', [$this->policyTopic()]];
                }
                if ($scenario === 'limited_ads' && $c === 0 && $i === 0) {
                    [$review, $raw, $topics] = [ReviewStatus::Limited, $this->limitedStatus(), ['Trademarks in ad text']];
                }
                if ($scenario === 'broken_landing_page' && $c === 1) {
                    // A seasonal page that was taken offline (see MockUrlProber).
                    $path = '/angebot-sommer';
                }

                $ads[] = new AdSnapshot(
                    externalId: $campaign['id'].'0'.($i + 1),
                    name: $campaign['name'].' – '.__('Ad').' '.$variant,
                    campaignId: $campaign['id'],
                    campaignName: $campaign['name'],
                    adGroupId: $campaign['id'].'9',
                    adGroupName: $campaign['name'],
                    isActive: true,
                    deliveryStatus: 'ENABLED',
                    reviewStatus: $review,
                    reviewStatusRaw: $raw,
                    policyTopics: $topics,
                    finalUrls: [$domain.$path],
                );
            }
        }

        return $ads;
    }

    public function token(AdConnection $connection): ?TokenInfo
    {
        return null;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function resetAttempts(): void
    {
        $this->attempts = 0;
    }

    protected function activeStatus(): string
    {
        return 'ENABLED';
    }

    protected function disabledStatus(): string
    {
        return 'SUSPENDED';
    }

    protected function approvedStatus(): string
    {
        return 'APPROVED';
    }

    protected function limitedStatus(): string
    {
        return 'APPROVED_LIMITED';
    }

    protected function policyTopic(): string
    {
        return 'Unreliable claims';
    }

    /** @throws AdPlatformException */
    protected function attempt(AdConnection $connection): void
    {
        $this->attempts++;

        if ($connection->mockScenario() === 'auth_failed') {
            throw new AdPlatformException(
                'Simulated: the access token was revoked by the account owner.', AdPlatformException::AUTH, 401,
            );
        }
    }
}

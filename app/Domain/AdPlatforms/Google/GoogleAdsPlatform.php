<?php

namespace App\Domain\AdPlatforms\Google;

use App\Domain\AdPlatforms\Contracts\AdPlatform;
use App\Domain\AdPlatforms\Data\AccountInfo;
use App\Domain\AdPlatforms\Data\AdSnapshot;
use App\Domain\AdPlatforms\Data\CampaignDay;
use App\Domain\AdPlatforms\Data\TokenInfo;
use App\Domain\AdPlatforms\Enums\ReviewStatus;
use App\Domain\AdPlatforms\Http\ResilientHttp;
use App\Enums\Platform;
use App\Models\AdConnection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/**
 * Google Ads via the REST interface and GAQL (API version pinned in
 * config/vigilo.php). Response fields arrive in lowerCamelCase.
 */
class GoogleAdsPlatform implements AdPlatform
{
    private readonly GoogleAdsClient $client;

    public function __construct(private readonly ResilientHttp $http)
    {
        $this->client = new GoogleAdsClient($http);
    }

    public function platform(): Platform
    {
        return Platform::Google;
    }

    public function account(AdConnection $connection): AccountInfo
    {
        $rows = $this->client->search($connection,
            'SELECT customer.id, customer.descriptive_name, customer.currency_code, customer.time_zone, customer.status FROM customer LIMIT 1'
        );
        $customer = (array) ($rows[0]['customer'] ?? []);
        $status = $customer['status'] ?? null;

        return new AccountInfo(
            name: $customer['descriptiveName'] ?? null,
            currency: $customer['currencyCode'] ?? null,
            timezone: $customer['timeZone'] ?? null,
            isActive: $status === null || $status === 'ENABLED',
            rawStatus: $status,
        );
    }

    public function dailyMetrics(AdConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $gaql = sprintf(
            "SELECT segments.date, campaign.id, campaign.name, metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.conversions FROM campaign WHERE segments.date BETWEEN '%s' AND '%s'",
            $from->toDateString(), $to->toDateString(),
        );

        $days = [];
        foreach ($this->client->search($connection, $gaql) as $row) {
            $metrics = (array) ($row['metrics'] ?? []);
            $days[] = new CampaignDay(
                date: (string) Arr::get($row, 'segments.date'),
                campaignId: (string) Arr::get($row, 'campaign.id'),
                campaignName: Arr::get($row, 'campaign.name'),
                spend: round(((int) ($metrics['costMicros'] ?? 0)) / 1_000_000, 2),
                impressions: (int) ($metrics['impressions'] ?? 0),
                clicks: (int) ($metrics['clicks'] ?? 0),
                conversions: (float) ($metrics['conversions'] ?? 0),
            );
        }

        return $days;
    }

    public function ads(AdConnection $connection): array
    {
        $ads = [];

        $gaql = "SELECT ad_group_ad.ad.id, ad_group_ad.ad.name, ad_group_ad.ad.type, ad_group_ad.status, ad_group_ad.ad.final_urls, ad_group_ad.policy_summary.approval_status, ad_group_ad.policy_summary.review_status, ad_group_ad.policy_summary.policy_topic_entries, ad_group.id, ad_group.name, campaign.id, campaign.name FROM ad_group_ad WHERE campaign.status = 'ENABLED' AND ad_group.status = 'ENABLED' AND ad_group_ad.status != 'REMOVED'";
        foreach ($this->client->search($connection, $gaql) as $row) {
            $ad = (array) Arr::get($row, 'adGroupAd.ad', []);
            $policy = (array) Arr::get($row, 'adGroupAd.policySummary', []);
            $approval = $policy['approvalStatus'] ?? null;

            $topics = [];
            foreach ((array) ($policy['policyTopicEntries'] ?? []) as $entry) {
                if (! empty($entry['topic'])) {
                    $topics[] = trim($entry['topic'].(isset($entry['type']) ? ' ('.$entry['type'].')' : ''));
                }
            }

            $ads[] = new AdSnapshot(
                externalId: (string) ($ad['id'] ?? ''),
                name: $ad['name'] ?? ($ad['type'] ?? null),
                campaignId: (string) Arr::get($row, 'campaign.id'),
                campaignName: Arr::get($row, 'campaign.name'),
                adGroupId: (string) Arr::get($row, 'adGroup.id'),
                adGroupName: Arr::get($row, 'adGroup.name'),
                isActive: Arr::get($row, 'adGroupAd.status') === 'ENABLED',
                deliveryStatus: Arr::get($row, 'adGroupAd.status'),
                reviewStatus: self::reviewStatus($approval, $policy['reviewStatus'] ?? null),
                reviewStatusRaw: $approval,
                policyTopics: array_values(array_unique($topics)),
                finalUrls: array_values(array_filter((array) ($ad['finalUrls'] ?? []), 'is_string')),
            );
        }

        // Performance Max has no ad_group_ad rows; its landing pages live on asset groups.
        $gaql = "SELECT asset_group.id, asset_group.name, asset_group.final_urls, asset_group.status, campaign.id, campaign.name FROM asset_group WHERE campaign.status = 'ENABLED' AND asset_group.status = 'ENABLED'";
        foreach ($this->client->search($connection, $gaql) as $row) {
            $group = (array) ($row['assetGroup'] ?? []);
            $ads[] = new AdSnapshot(
                externalId: 'asset_group:'.($group['id'] ?? ''),
                name: $group['name'] ?? null,
                campaignId: (string) Arr::get($row, 'campaign.id'),
                campaignName: Arr::get($row, 'campaign.name'),
                adGroupId: null,
                adGroupName: null,
                isActive: true,
                deliveryStatus: $group['status'] ?? null,
                reviewStatus: ReviewStatus::Unknown,
                reviewStatusRaw: null,
                policyTopics: [],
                finalUrls: array_values(array_filter((array) ($group['finalUrls'] ?? []), 'is_string')),
            );
        }

        return $ads;
    }

    public function token(AdConnection $connection): ?TokenInfo
    {
        // Google refresh tokens do not expire on a schedule; a revoked token
        // surfaces as an auth failure on the next fetch instead.
        return null;
    }

    public function attempts(): int
    {
        return $this->http->attempts();
    }

    public function resetAttempts(): void
    {
        $this->http->resetAttempts();
    }

    public static function reviewStatus(?string $approval, ?string $review): ReviewStatus
    {
        return match ($approval) {
            'APPROVED' => ReviewStatus::Approved,
            'APPROVED_LIMITED', 'AREA_OF_INTEREST_ONLY' => ReviewStatus::Limited,
            'DISAPPROVED' => ReviewStatus::Disapproved,
            default => in_array($review, ['REVIEW_IN_PROGRESS', 'ELIGIBLE_MAY_SERVE', 'UNDER_APPEAL'], true)
                ? ReviewStatus::UnderReview
                : ReviewStatus::Unknown,
        };
    }
}

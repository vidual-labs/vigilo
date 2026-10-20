<?php

namespace App\Domain\AdPlatforms\Meta;

use App\Domain\AdPlatforms\Contracts\AdPlatform;
use App\Domain\AdPlatforms\Data\AccountInfo;
use App\Domain\AdPlatforms\Data\AdSnapshot;
use App\Domain\AdPlatforms\Data\CampaignDay;
use App\Domain\AdPlatforms\Data\TokenInfo;
use App\Domain\AdPlatforms\Enums\ReviewStatus;
use App\Domain\AdPlatforms\Exceptions\AdPlatformException;
use App\Domain\AdPlatforms\Http\ResilientHttp;
use App\Enums\Platform;
use App\Models\AdConnection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/** Meta Marketing API adapter (system-user token with ads_read). */
class MetaAdsPlatform implements AdPlatform
{
    /** Graph account_status codes; only 1 delivers ads. */
    public const ACCOUNT_STATUSES = [
        1 => 'ACTIVE', 2 => 'DISABLED', 3 => 'UNSETTLED', 7 => 'PENDING_RISK_REVIEW',
        8 => 'PENDING_SETTLEMENT', 9 => 'IN_GRACE_PERIOD', 100 => 'PENDING_CLOSURE', 101 => 'CLOSED',
    ];

    private const REVIEWABLE_STATUSES = ['ACTIVE', 'WITH_ISSUES', 'DISAPPROVED', 'PENDING_REVIEW', 'IN_PROCESS', 'PREAPPROVED'];

    private readonly MetaGraphClient $client;

    public function __construct(private readonly ResilientHttp $http)
    {
        $this->client = new MetaGraphClient($http);
    }

    public function platform(): Platform
    {
        return Platform::Meta;
    }

    public function account(AdConnection $connection): AccountInfo
    {
        $json = $this->client->get($connection, $connection->account_id, [
            'fields' => 'name,currency,timezone_name,account_status',
        ]);
        $status = (int) ($json['account_status'] ?? 1);

        return new AccountInfo(
            name: $json['name'] ?? null,
            currency: $json['currency'] ?? null,
            timezone: $json['timezone_name'] ?? null,
            isActive: $status === 1,
            rawStatus: self::ACCOUNT_STATUSES[$status] ?? (string) $status,
        );
    }

    public function dailyMetrics(AdConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->client->getAll($connection, $connection->account_id.'/insights', [
            'level' => 'campaign',
            'time_increment' => 1,
            'time_range' => json_encode(['since' => $from->toDateString(), 'until' => $to->toDateString()]),
            'fields' => 'campaign_id,campaign_name,spend,impressions,clicks,actions',
            'limit' => 500,
        ]);

        $actionTypes = (array) config('vigilo.meta.conversion_action_types', ['lead']);

        return array_map(fn (array $row) => new CampaignDay(
            date: (string) ($row['date_start'] ?? ''),
            campaignId: (string) ($row['campaign_id'] ?? ''),
            campaignName: $row['campaign_name'] ?? null,
            spend: round((float) ($row['spend'] ?? 0), 2),
            impressions: (int) ($row['impressions'] ?? 0),
            clicks: (int) ($row['clicks'] ?? 0),
            conversions: self::conversions((array) ($row['actions'] ?? []), $actionTypes),
        ), $rows);
    }

    public function ads(AdConnection $connection): array
    {
        $rows = $this->client->getAll($connection, $connection->account_id.'/ads', [
            'fields' => 'id,name,effective_status,configured_status,campaign{id,name,effective_status},adset{id,name,effective_status},creative{object_story_spec,asset_feed_spec,link_url},ad_review_feedback,issues_info',
            'effective_status' => json_encode(self::REVIEWABLE_STATUSES),
            'limit' => 200,
        ]);

        return array_map(function (array $row) {
            $effective = $row['effective_status'] ?? null;
            $parentsActive = in_array(Arr::get($row, 'campaign.effective_status'), ['ACTIVE', 'WITH_ISSUES'], true)
                && in_array(Arr::get($row, 'adset.effective_status'), ['ACTIVE', 'WITH_ISSUES'], true);

            return new AdSnapshot(
                externalId: (string) ($row['id'] ?? ''),
                name: $row['name'] ?? null,
                campaignId: Arr::get($row, 'campaign.id'),
                campaignName: Arr::get($row, 'campaign.name'),
                adGroupId: Arr::get($row, 'adset.id'),
                adGroupName: Arr::get($row, 'adset.name'),
                isActive: ($row['configured_status'] ?? null) === 'ACTIVE' && $parentsActive,
                deliveryStatus: $effective,
                reviewStatus: self::reviewStatus($effective),
                reviewStatusRaw: $effective,
                policyTopics: self::policyTopics($row),
                finalUrls: self::finalUrls((array) ($row['creative'] ?? [])),
            );
        }, $rows);
    }

    public function token(AdConnection $connection): ?TokenInfo
    {
        $token = $this->client->token($connection);
        // A token may inspect itself; no app access token needed.
        $json = $this->client->get($connection, 'debug_token', ['input_token' => $token]);
        $data = (array) ($json['data'] ?? []);

        if (! ($data['is_valid'] ?? false)) {
            throw new AdPlatformException(
                'Meta access token is invalid: '.Arr::get($data, 'error.message', 'expired or revoked'),
                AdPlatformException::AUTH,
            );
        }

        // 0 means "never"; the earlier of token and data-access expiry wins.
        $expiries = array_filter([(int) ($data['expires_at'] ?? 0), (int) ($data['data_access_expires_at'] ?? 0)]);

        return new TokenInfo(
            isValid: true,
            expiresAt: $expiries ? CarbonImmutable::createFromTimestampUTC(min($expiries)) : null,
            scopes: array_values(array_map('strval', (array) ($data['scopes'] ?? []))),
        );
    }

    public function attempts(): int
    {
        return $this->http->attempts();
    }

    public function resetAttempts(): void
    {
        $this->http->resetAttempts();
    }

    /**
     * @param  list<array<string, mixed>>|array<int, mixed>  $actions
     * @param  array<int, mixed>  $types
     */
    public static function conversions(array $actions, array $types): float
    {
        $byType = [];
        foreach ($actions as $action) {
            if (is_array($action) && isset($action['action_type'])) {
                $byType[$action['action_type']] = (float) ($action['value'] ?? 0);
            }
        }
        foreach ($types as $type) {
            if (array_key_exists($type, $byType)) {
                return $byType[$type];
            }
        }

        return 0.0;
    }

    public static function reviewStatus(?string $effectiveStatus): ReviewStatus
    {
        return match ($effectiveStatus) {
            'DISAPPROVED' => ReviewStatus::Disapproved,
            'WITH_ISSUES' => ReviewStatus::Limited,
            'PENDING_REVIEW', 'IN_PROCESS' => ReviewStatus::UnderReview,
            'ACTIVE', 'PREAPPROVED' => ReviewStatus::Approved,
            default => ReviewStatus::Unknown,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private static function policyTopics(array $row): array
    {
        $topics = array_keys((array) Arr::get($row, 'ad_review_feedback.global', []));
        foreach ((array) ($row['issues_info'] ?? []) as $issue) {
            if (! empty($issue['error_summary'])) {
                $topics[] = (string) $issue['error_summary'];
            }
        }

        return array_values(array_unique(array_map('strval', $topics)));
    }

    /**
     * Landing pages from the creative. Instant-form lead ads have none (their
     * "link" points back to Facebook), so those are dropped.
     *
     * @param  array<string, mixed>  $creative
     * @return list<string>
     */
    public static function finalUrls(array $creative): array
    {
        $candidates = [
            Arr::get($creative, 'object_story_spec.link_data.link'),
            Arr::get($creative, 'object_story_spec.link_data.call_to_action.value.link'),
            Arr::get($creative, 'object_story_spec.video_data.call_to_action.value.link'),
            Arr::get($creative, 'object_story_spec.template_data.link'),
            $creative['link_url'] ?? null,
        ];
        foreach ((array) Arr::get($creative, 'asset_feed_spec.link_urls', []) as $link) {
            $candidates[] = $link['website_url'] ?? null;
        }

        $urls = [];
        foreach ($candidates as $url) {
            if (! is_string($url) || ! preg_match('#^https?://#i', $url)) {
                continue;
            }
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if (preg_match('/(^|\.)(facebook\.com|fb\.me|fb\.com|instagram\.com|messenger\.com|whatsapp\.com)$/', $host)) {
                continue;
            }
            $urls[] = $url;
        }

        return array_values(array_unique($urls));
    }
}

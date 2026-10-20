<?php

namespace App\Domain\AdPlatforms\Services;

use App\Domain\AdPlatforms\AdPlatformRegistry;
use App\Domain\AdPlatforms\Contracts\AdPlatform;
use App\Domain\AdPlatforms\Data\AdSnapshot;
use App\Domain\AdPlatforms\Data\CampaignDay;
use App\Domain\AdPlatforms\Enums\ConnectionStatus;
use App\Domain\AdPlatforms\Exceptions\AdPlatformException;
use App\Models\Ad;
use App\Models\AdConnection;
use App\Models\AdMetricDaily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pulls one connection's data through its adapter and stores it: account
 * info, daily campaign metrics, ads, and token expiry. Updates the
 * connection's health fields that the connection-health check reads.
 */
class AdDataSync
{
    public const KINDS = ['account', 'metrics', 'ads', 'token'];

    public function __construct(
        private readonly AdPlatformRegistry $registry,
        private readonly FetchLogger $logger,
    ) {}

    /**
     * @param  list<string>  $kinds
     * @return array{ok: bool, error: ?string, error_code: ?string, rows: array<string, int>}
     */
    public function sync(AdConnection $connection, array $kinds = self::KINDS): array
    {
        $adapter = $this->registry->for($connection->platform);
        $connection->loadMissing('client.budgets');
        $rows = [];
        $failure = null;

        foreach ($kinds as $kind) {
            $adapter->resetAttempts();

            try {
                $rows[$kind] = $this->logger->record(
                    $connection, $connection->client_id, $kind,
                    fn () => $this->{'sync'.ucfirst($kind)}($adapter, $connection),
                    fn () => $adapter->attempts(),
                    fn (int $count) => $count,
                );
            } catch (AdPlatformException $e) {
                $failure = $e;
                if ($e->isAuth() || $e->errorCode === AdPlatformException::CONFIG) {
                    break; // pointless to hit the next endpoint with the same credentials
                }
            }
        }

        $this->recordOutcome($connection, $failure);

        return [
            'ok' => $failure === null,
            'error' => $failure?->getMessage(),
            'error_code' => $failure?->errorCode,
            'rows' => $rows,
        ];
    }

    /**
     * Synchronous "Test connection": account + token only, logged as `test`.
     *
     * @return array{ok: bool, message: string, account: array<string, mixed>}
     */
    public function test(AdConnection $connection): array
    {
        $adapter = $this->registry->for($connection->platform);
        $connection->loadMissing('client');
        $adapter->resetAttempts();

        try {
            $account = $this->logger->record($connection, $connection->client_id, 'test',
                function () use ($adapter, $connection) {
                    $info = $adapter->account($connection);
                    $token = $adapter->token($connection);

                    return ['info' => $info, 'token' => $token];
                },
                fn () => $adapter->attempts(),
            );
        } catch (AdPlatformException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'account' => []];
        }

        $connection->forceFill([
            'settings' => array_merge($connection->settings ?? [], $account['info']->toSettings()),
            'token_expires_at' => $account['token']?->expiresAt,
            'token_checked_at' => $account['token'] ? now() : $connection->token_checked_at,
        ])->save();

        return ['ok' => true, 'message' => (string) $account['info']->name, 'account' => $account['info']->toSettings()];
    }

    private function syncAccount(AdPlatform $adapter, AdConnection $connection): int
    {
        $info = $adapter->account($connection);
        $connection->forceFill([
            'settings' => array_merge($connection->settings ?? [], $info->toSettings()),
        ])->save();

        return 1;
    }

    private function syncMetrics(AdPlatform $adapter, AdConnection $connection): int
    {
        $tz = $connection->client->timezone ?? config('vigilo.timezone');
        $to = CarbonImmutable::now($tz)->startOfDay();
        $from = $to->subDays(max(1, (int) config('vigilo.fetch.metrics_days', 40)));

        $days = $adapter->dailyMetrics($connection, $from, $to);

        DB::transaction(function () use ($connection, $from, $to, $days) {
            // The fetched window replaces what we had: platforms omit zero rows
            // and re-attribute conversions to earlier days.
            AdMetricDaily::query()
                ->where('ad_connection_id', $connection->id)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->delete();

            $now = now();
            $rows = array_map(fn (CampaignDay $day) => [
                'ad_connection_id' => $connection->id,
                'client_id' => $connection->client_id,
                'platform' => $connection->platform->value,
                'date' => $day->date,
                'campaign_id' => $day->campaignId,
                'campaign_name' => $day->campaignName ? mb_substr($day->campaignName, 0, 255) : null,
                'spend' => $day->spend,
                'impressions' => $day->impressions,
                'clicks' => $day->clicks,
                'conversions' => $day->conversions,
                'created_at' => $now,
                'updated_at' => $now,
            ], $days);

            foreach (array_chunk($this->mergeDuplicates($rows), 500) as $chunk) {
                AdMetricDaily::query()->insert($chunk);
            }
        });

        return count($days);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function mergeDuplicates(array $rows): array
    {
        $merged = [];
        foreach ($rows as $row) {
            $key = $row['date'].'|'.$row['campaign_id'];
            if (! isset($merged[$key])) {
                $merged[$key] = $row;

                continue;
            }
            foreach (['spend', 'impressions', 'clicks', 'conversions'] as $metric) {
                $merged[$key][$metric] += $row[$metric];
            }
        }

        return array_values($merged);
    }

    private function syncAds(AdPlatform $adapter, AdConnection $connection): int
    {
        $snapshots = $adapter->ads($connection);
        $seenAt = now();

        DB::transaction(function () use ($connection, $snapshots, $seenAt) {
            foreach ($snapshots as $ad) {
                /** @var AdSnapshot $ad */
                Ad::query()->updateOrCreate(
                    ['ad_connection_id' => $connection->id, 'external_id' => $ad->externalId],
                    [
                        'client_id' => $connection->client_id,
                        'platform' => $connection->platform->value,
                        'name' => $ad->name ? mb_substr($ad->name, 0, 255) : null,
                        'campaign_id' => $ad->campaignId,
                        'campaign_name' => $ad->campaignName ? mb_substr($ad->campaignName, 0, 255) : null,
                        'ad_group_id' => $ad->adGroupId,
                        'ad_group_name' => $ad->adGroupName ? mb_substr($ad->adGroupName, 0, 255) : null,
                        'is_active' => $ad->isActive,
                        'delivery_status' => $ad->deliveryStatus,
                        'review_status' => $ad->reviewStatus->value,
                        'review_status_raw' => $ad->reviewStatusRaw,
                        'policy_topics' => $ad->policyTopics,
                        'final_urls' => $ad->finalUrls,
                        'last_seen_at' => $seenAt,
                    ],
                );
            }

            // Ads the platform no longer lists (removed, campaign paused) go.
            Ad::query()
                ->where('ad_connection_id', $connection->id)
                ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $seenAt))
                ->delete();
        });

        return count($snapshots);
    }

    private function syncToken(AdPlatform $adapter, AdConnection $connection): int
    {
        $token = $adapter->token($connection);
        if ($token === null) {
            return 0;
        }

        $connection->forceFill([
            'token_expires_at' => $token->expiresAt,
            'token_checked_at' => now(),
        ])->save();

        return 1;
    }

    private function recordOutcome(AdConnection $connection, ?AdPlatformException $failure): void
    {
        $connection->last_attempt_at = now();

        if ($failure === null) {
            $connection->forceFill([
                'status' => ConnectionStatus::Ok,
                'last_success_at' => now(),
                'last_error' => null,
                'last_error_code' => null,
                'consecutive_failures' => 0,
            ]);
        } else {
            $connection->forceFill([
                'status' => $failure->isAuth() ? ConnectionStatus::AuthFailed : ConnectionStatus::Failing,
                'last_error' => mb_substr($failure->getMessage(), 0, 2000),
                'last_error_code' => $failure->errorCode,
                'consecutive_failures' => $connection->consecutive_failures + 1,
            ]);
        }

        $connection->save();
    }
}

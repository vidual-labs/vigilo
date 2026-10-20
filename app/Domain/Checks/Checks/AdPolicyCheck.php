<?php

namespace App\Domain\Checks\Checks;

use App\Domain\AdPlatforms\Enums\ReviewStatus;
use App\Domain\Checks\CheckResult;
use App\Domain\Checks\Snapshot\AdState;
use App\Domain\Checks\Snapshot\ClientSnapshot;
use App\Domain\Findings\Enums\Severity;

/**
 * Disapproved and limited ads in running campaigns (Google policy
 * approval status, Meta effective_status). One finding per connection and
 * kind, listing the ads, so 30 disapproved ads are one alert, not 30.
 * Critical when a campaign has no approved ad left to deliver.
 */
class AdPolicyCheck extends BaseCheck
{
    private const MAX_LISTED = 50;

    public function key(): string
    {
        return 'ad_policy';
    }

    public function fields(): array
    {
        return ['limited_severity' => 'severity'];
    }

    public function run(ClientSnapshot $snapshot, array $thresholds): CheckResult
    {
        $result = new CheckResult;

        foreach ($snapshot->activeConnections() as $connection) {
            if (! $snapshot->isFresh($connection)) {
                $result->keep($this->subjectKey($snapshot, 'conn:'.$connection->id));

                continue;
            }

            $ads = array_values(array_filter($snapshot->ads, fn (AdState $ad) => $ad->connectionId === $connection->id && $ad->isActive));
            $disapproved = array_values(array_filter($ads, fn (AdState $ad) => $ad->reviewStatus === ReviewStatus::Disapproved));
            $limited = array_values(array_filter($ads, fn (AdState $ad) => $ad->reviewStatus === ReviewStatus::Limited));

            if ($disapproved !== []) {
                $blocked = $this->blockedCampaigns($ads);
                $result->add($this->draft($snapshot, 'conn:'.$connection->id.':disapproved',
                    $blocked === [] ? Severity::Warning : Severity::Critical,
                    $blocked === [] ? 'disapproved' : 'campaign_blocked',
                    ['account' => $connection->name, 'count' => count($disapproved), 'campaigns' => count($blocked)],
                    ['account' => $connection->name, 'blocked_campaigns' => $blocked, 'ads' => $this->listAds($disapproved)],
                    $connection->platform,
                ));
            }

            if ($limited !== []) {
                $result->add($this->draft($snapshot, 'conn:'.$connection->id.':limited',
                    Severity::tryFrom((string) $thresholds['limited_severity']) ?? Severity::Info,
                    'limited',
                    ['account' => $connection->name, 'count' => count($limited)],
                    ['account' => $connection->name, 'ads' => $this->listAds($limited)],
                    $connection->platform,
                ));
            }
        }

        return $result;
    }

    /**
     * Campaigns whose every active ad is disapproved — they cannot deliver.
     *
     * @param  list<AdState>  $ads
     * @return list<string>
     */
    private function blockedCampaigns(array $ads): array
    {
        $byCampaign = [];
        foreach ($ads as $ad) {
            if ($ad->reviewStatus === ReviewStatus::Unknown || $ad->campaignId === null) {
                continue; // e.g. Performance Max asset groups carry no review status
            }
            $byCampaign[$ad->campaignId]['name'] = $ad->campaignName ?? $ad->campaignId;
            $byCampaign[$ad->campaignId]['statuses'][] = $ad->reviewStatus;
        }

        $blocked = [];
        foreach ($byCampaign as $campaign) {
            $statuses = array_unique(array_map(fn (ReviewStatus $s) => $s->value, $campaign['statuses']));
            if ($statuses === [ReviewStatus::Disapproved->value]) {
                $blocked[] = (string) $campaign['name'];
            }
        }

        return $blocked;
    }

    /**
     * @param  list<AdState>  $ads
     * @return list<array<string, string>>
     */
    private function listAds(array $ads): array
    {
        return array_map(fn (AdState $ad) => [
            'ad' => (string) ($ad->name ?? $ad->externalId),
            'campaign' => (string) ($ad->campaignName ?? '—'),
            'status' => (string) ($ad->reviewStatusRaw ?? $ad->reviewStatus->value),
            'reason' => implode(', ', $ad->policyTopics) ?: '—',
        ], array_slice($ads, 0, self::MAX_LISTED));
    }
}

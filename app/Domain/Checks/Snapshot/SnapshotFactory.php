<?php

namespace App\Domain\Checks\Snapshot;

use App\Domain\AdPlatforms\Enums\ConnectionStatus;
use App\Domain\AdPlatforms\Enums\ReviewStatus;
use App\Models\Ad;
use App\Models\AdConnection;
use App\Models\AdMetricDaily;
use App\Models\Client;
use App\Models\Lead;
use App\Models\UrlProbe;
use Carbon\CarbonImmutable;

/** Loads a ClientSnapshot from the database. The only place checks' data comes from. */
class SnapshotFactory
{
    /** Longest look-back any check needs: CPL 7 + 28 days, plus slack. */
    public const LOOKBACK_DAYS = 45;

    public function build(Client $client, ?CarbonImmutable $now = null): ClientSnapshot
    {
        $tz = $client->timezone ?: (string) config('vigilo.timezone');
        $now = ($now ?? CarbonImmutable::now())->setTimezone($tz);
        $from = $now->startOfDay()->subDays(self::LOOKBACK_DAYS);
        // The budget check needs the whole month even early in a long window.
        $metricsFrom = $from->min($now->startOfMonth())->toDateString();

        $client->loadMissing(['connections', 'leadSource', 'budgets']);

        $connections = $client->connections->map(fn (AdConnection $c) => new ConnectionState(
            id: $c->id,
            platform: $c->platform,
            name: $c->displayName(),
            isActive: $c->is_active,
            status: $c->status,
            lastSuccessAt: self::immutable($c->last_success_at),
            lastAttemptAt: self::immutable($c->last_attempt_at),
            lastErrorCode: $c->last_error_code,
            lastError: $c->last_error,
            tokenExpiresAt: self::immutable($c->token_expires_at),
            accountActive: $c->setting('account_active'),
            accountStatus: $c->setting('account_status'),
            createdAt: self::immutable($c->created_at),
        ))->values()->all();

        $metrics = [];
        AdMetricDaily::query()
            ->where('client_id', $client->id)
            ->where('date', '>=', $metricsFrom)
            ->selectRaw('ad_connection_id, date, sum(spend) as spend, sum(conversions) as conversions')
            ->groupBy('ad_connection_id', 'date')
            ->toBase()
            ->get()
            ->each(function (object $row) use (&$metrics) {
                $date = substr((string) $row->date, 0, 10);
                $metrics[(int) $row->ad_connection_id][$date] = [
                    'spend' => (float) $row->spend,
                    'conversions' => (float) $row->conversions,
                ];
            });

        $ads = Ad::query()->where('client_id', $client->id)->get()->map(fn (Ad $ad) => new AdState(
            connectionId: $ad->ad_connection_id,
            externalId: $ad->external_id,
            name: $ad->name,
            campaignId: $ad->campaign_id,
            campaignName: $ad->campaign_name,
            isActive: $ad->is_active,
            reviewStatus: $ad->review_status ?? ReviewStatus::Unknown,
            reviewStatusRaw: $ad->review_status_raw,
            policyTopics: array_values($ad->policy_topics ?? []),
        ))->all();

        $source = $client->leadSource;
        $leadSource = $source && $source->is_active ? new LeadSourceState(
            id: $source->id,
            type: $source->type->value,
            status: $source->status ?? ConnectionStatus::Unknown,
            lastSuccessAt: self::immutable($source->last_success_at),
            lastAttemptAt: self::immutable($source->last_attempt_at),
            lastErrorCode: $source->last_error_code,
            lastError: $source->last_error,
            createdAt: self::immutable($source->created_at),
        ) : null;

        $leads = $leadSource === null ? [] : Lead::query()
            ->where('client_id', $client->id)
            ->where('submitted_at', '>=', $from->utc())
            ->get(['submitted_at', 'status', 'source_type', 'meta_campaign_id', 'meta_adset_id', 'meta_ad_id', 'has_gclid'])
            ->map(fn (Lead $lead) => new LeadFact(
                submittedAt: CarbonImmutable::instance($lead->submitted_at),
                countable: $lead->status->isCountable(),
                platform: $lead->attributedPlatform(),
            ))->all();

        $budgets = [];
        foreach ($client->budgets as $budget) {
            $budgets[$budget->platform->value] = (float) $budget->monthly_amount;
        }

        $probes = UrlProbe::query()->where('client_id', $client->id)->get()->map(fn (UrlProbe $p) => new ProbeState(
            url: $p->url,
            urlHash: $p->url_hash,
            origin: $p->origin,
            finalUrl: $p->final_url,
            statusCode: $p->status_code,
            redirectChain: array_values($p->redirect_chain ?? []),
            redirectCount: $p->redirect_count,
            responseMs: $p->response_ms,
            tlsExpiresAt: self::immutable($p->tls_expires_at),
            error: $p->error,
            consecutiveFailures: $p->consecutive_failures,
            checkedAt: self::immutable($p->checked_at),
        ))->all();

        return new ClientSnapshot(
            clientId: $client->id,
            clientName: $client->name,
            currency: $client->currency ?: 'EUR',
            now: $now,
            connections: $connections,
            metrics: $metrics,
            ads: $ads,
            leadSource: $leadSource,
            leads: $leads,
            budgets: $budgets,
            probes: $probes,
        );
    }

    private static function immutable(?\DateTimeInterface $value): ?CarbonImmutable
    {
        return $value ? CarbonImmutable::instance($value) : null;
    }
}

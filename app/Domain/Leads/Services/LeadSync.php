<?php

namespace App\Domain\Leads\Services;

use App\Domain\AdPlatforms\Enums\ConnectionStatus;
use App\Domain\AdPlatforms\Exceptions\AdPlatformException;
use App\Domain\AdPlatforms\Services\FetchLogger;
use App\Domain\Leads\Data\LeadMetadata;
use App\Domain\Leads\LeadSourceRegistry;
use App\Models\Lead;
use App\Models\LeadSource;
use Illuminate\Support\Facades\DB;

/**
 * Pulls a client's lead metadata page by page, upserts it, applies
 * deletions from the source, and advances the cursor after every page so a
 * failure half-way never refetches from the start.
 */
class LeadSync
{
    public function __construct(
        private readonly LeadSourceRegistry $registry,
        private readonly FetchLogger $logger,
    ) {}

    /** @return array{ok: bool, error: ?string, rows: int} */
    public function sync(LeadSource $source): array
    {
        $adapter = $this->registry->for($source->type);
        $adapter->resetAttempts();
        $source->loadMissing('client');
        $maxPages = (int) config('vigilo.lodgely.max_pages_per_fetch', 40);

        try {
            $count = $this->logger->record($source, $source->client_id, 'leads', function () use ($adapter, $source, $maxPages) {
                $count = 0;
                $page = 0;
                do {
                    $result = $adapter->fetch($source, $source->cursor);
                    DB::transaction(function () use ($source, $result) {
                        $this->store($source, $result->leads);
                        if ($result->deletedIds) {
                            Lead::query()->where('lead_source_id', $source->id)->whereIn('external_id', $result->deletedIds)->delete();
                        }
                        $source->forceFill(['cursor' => $result->nextCursor])->save();
                    });
                    $count += count($result->leads);
                } while ($result->hasMore && ++$page < $maxPages);

                return $count;
            }, fn () => $adapter->attempts(), fn (int $count) => $count);
        } catch (AdPlatformException $e) {
            $source->forceFill([
                'status' => $e->isAuth() ? ConnectionStatus::AuthFailed : ConnectionStatus::Failing,
                'last_attempt_at' => now(),
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
                'last_error_code' => $e->errorCode,
                'consecutive_failures' => $source->consecutive_failures + 1,
            ])->save();

            return ['ok' => false, 'error' => $e->getMessage(), 'rows' => 0];
        }

        $source->forceFill([
            'status' => ConnectionStatus::Ok,
            'last_attempt_at' => now(),
            'last_success_at' => now(),
            'last_error' => null,
            'last_error_code' => null,
            'consecutive_failures' => 0,
        ])->save();

        return ['ok' => true, 'error' => null, 'rows' => $count];
    }

    /** @return array{ok: bool, message: string} */
    public function test(LeadSource $source): array
    {
        $adapter = $this->registry->for($source->type);
        $adapter->resetAttempts();

        try {
            $message = $this->logger->record($source, $source->client_id, 'test', fn () => $adapter->test($source), fn () => $adapter->attempts());
        } catch (AdPlatformException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return ['ok' => true, 'message' => $message];
    }

    /** @param list<LeadMetadata> $leads */
    private function store(LeadSource $source, array $leads): void
    {
        $now = now();
        $rows = [];
        foreach ($leads as $lead) {
            $row = $lead->toRow();
            $row['flags'] = json_encode($row['flags']);
            $rows[$lead->externalId] = [
                'client_id' => $source->client_id,
                'lead_source_id' => $source->id,
                ...$row,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            Lead::query()->upsert(
                $chunk,
                ['lead_source_id', 'external_id'],
                ['submitted_at', 'status', 'status_changed_at', 'source_type', 'meta_campaign_id', 'meta_adset_id',
                    'meta_ad_id', 'meta_form_id', 'has_gclid', 'flags', 'updated_at'],
            );
        }
    }
}

<?php

namespace App\Domain\AdPlatforms\Contracts;

use App\Models\AdConnection;
use Carbon\CarbonImmutable;

/**
 * Phase 2 seam (lead-quality miner, see ROADMAP.md VG-6): search-terms report
 * for negative-keyword and expansion suggestions. Declared so the miner can
 * be added without touching the Phase 1 contract; nothing implements it yet.
 */
interface ProvidesSearchTerms
{
    /**
     * @return list<array{campaign_id: string, ad_group_id: string, search_term: string, keyword: ?string, clicks: int, cost: float, conversions: float}>
     */
    public function searchTerms(AdConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array;
}

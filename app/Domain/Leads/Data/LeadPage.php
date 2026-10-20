<?php

namespace App\Domain\Leads\Data;

final readonly class LeadPage
{
    /**
     * @param  list<LeadMetadata>  $leads
     * @param  list<string>  $deletedIds  leads erased at the source (GDPR) — removed here too
     */
    public function __construct(
        public array $leads,
        public ?string $nextCursor,
        public bool $hasMore,
        public array $deletedIds = [],
    ) {}
}

<?php

namespace App\Domain\Checks\Snapshot;

use App\Domain\AdPlatforms\Enums\ReviewStatus;

final readonly class AdState
{
    /** @param list<string> $policyTopics */
    public function __construct(
        public int $connectionId,
        public string $externalId,
        public ?string $name,
        public ?string $campaignId,
        public ?string $campaignName,
        public bool $isActive,
        public ReviewStatus $reviewStatus,
        public ?string $reviewStatusRaw = null,
        public array $policyTopics = [],
    ) {}
}

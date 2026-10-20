<?php

namespace App\Domain\AdPlatforms\Data;

use App\Domain\AdPlatforms\Enums\ReviewStatus;

final readonly class AdSnapshot
{
    /**
     * @param  list<string>  $policyTopics
     * @param  list<string>  $finalUrls
     */
    public function __construct(
        public string $externalId,
        public ?string $name,
        public ?string $campaignId,
        public ?string $campaignName,
        public ?string $adGroupId,
        public ?string $adGroupName,
        public bool $isActive,
        public ?string $deliveryStatus,
        public ReviewStatus $reviewStatus,
        public ?string $reviewStatusRaw,
        public array $policyTopics,
        public array $finalUrls,
    ) {}
}

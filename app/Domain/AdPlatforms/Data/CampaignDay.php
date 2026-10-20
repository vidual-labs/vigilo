<?php

namespace App\Domain\AdPlatforms\Data;

final readonly class CampaignDay
{
    public function __construct(
        public string $date,
        public string $campaignId,
        public ?string $campaignName,
        public float $spend,
        public int $impressions,
        public int $clicks,
        public float $conversions,
    ) {}
}

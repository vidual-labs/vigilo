<?php

namespace App\Domain\AdPlatforms\Mock;

use App\Domain\AdPlatforms\Data\TokenInfo;
use App\Enums\Platform;
use App\Models\AdConnection;
use Carbon\CarbonImmutable;

class MockMetaAdsPlatform extends MockAdPlatform
{
    public function platform(): Platform
    {
        return Platform::Meta;
    }

    public function token(AdConnection $connection): ?TokenInfo
    {
        $this->attempt($connection);

        // Fixed relative to "now" so the demo always shows the same warning.
        return match ($connection->mockScenario()) {
            'token_expiring' => new TokenInfo(true, CarbonImmutable::now()->addDays(6)->startOfHour(), ['ads_read']),
            default => new TokenInfo(true, null, ['ads_read']),
        };
    }

    protected function campaigns(): array
    {
        return [
            ['id' => '23850110', 'name' => 'Leads | Entscheider DACH', 'share' => 0.6, 'path' => '/angebot'],
            ['id' => '23850120', 'name' => 'Retargeting | Website-Besucher', 'share' => 0.4, 'path' => '/beratung'],
        ];
    }

    protected function baseCpl(): float
    {
        return 31.0;
    }

    protected function baseCpc(): float
    {
        return 0.95;
    }

    protected function accountName(AdConnection $connection): string
    {
        return ($connection->client->name ?? 'Client').' – Meta';
    }

    protected function activeStatus(): string
    {
        return 'ACTIVE';
    }

    protected function disabledStatus(): string
    {
        return 'DISABLED';
    }

    protected function approvedStatus(): string
    {
        return 'ACTIVE';
    }

    protected function limitedStatus(): string
    {
        return 'WITH_ISSUES';
    }

    protected function policyTopic(): string
    {
        return 'Personal attributes';
    }
}

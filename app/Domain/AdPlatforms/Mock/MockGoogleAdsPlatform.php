<?php

namespace App\Domain\AdPlatforms\Mock;

use App\Enums\Platform;
use App\Models\AdConnection;

class MockGoogleAdsPlatform extends MockAdPlatform
{
    public function platform(): Platform
    {
        return Platform::Google;
    }

    protected function campaigns(): array
    {
        return [
            ['id' => '20110', 'name' => 'Search | Brand', 'share' => 0.2, 'path' => '/'],
            ['id' => '20120', 'name' => 'Search | Leistungen DACH', 'share' => 0.55, 'path' => '/leistungen'],
            ['id' => '20130', 'name' => 'Performance Max | Leads', 'share' => 0.25, 'path' => '/kontakt'],
        ];
    }

    protected function baseCpl(): float
    {
        return 48.0;
    }

    protected function baseCpc(): float
    {
        return 2.6;
    }

    protected function accountName(AdConnection $connection): string
    {
        return ($connection->client->name ?? 'Client').' – Google Ads';
    }
}

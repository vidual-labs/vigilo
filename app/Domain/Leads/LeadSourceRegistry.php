<?php

namespace App\Domain\Leads;

use App\Domain\Leads\Contracts\LeadSource;
use App\Domain\Leads\Enums\LeadSourceType;
use App\Domain\Leads\Lodgely\LodgelyLeadSource;
use App\Domain\Leads\Mock\MockLeadSource;
use Illuminate\Contracts\Container\Container;

class LeadSourceRegistry
{
    public function __construct(private readonly Container $container) {}

    public function for(LeadSourceType $type): LeadSource
    {
        // Mock mode simulates every integration, lodgely included.
        if (config('vigilo.mock_mode') || $type === LeadSourceType::Mock) {
            return $this->container->make(MockLeadSource::class);
        }

        return $this->container->make(LodgelyLeadSource::class);
    }

    /** @return list<LeadSourceType> types an operator can pick right now */
    public static function availableTypes(): array
    {
        $types = [];
        if (config('vigilo.mock_mode')) {
            $types[] = LeadSourceType::Mock;
        }
        if (config('vigilo.lodgely.enabled') || config('vigilo.mock_mode')) {
            $types[] = LeadSourceType::Lodgely;
        }

        return $types;
    }
}

<?php

namespace App\Domain\Leads\Contracts;

use App\Domain\AdPlatforms\Exceptions\AdPlatformException;
use App\Domain\Leads\Data\LeadPage;
use App\Models\LeadSource as LeadSourceModel;

/**
 * Where a client's leads come from (lodgely, or the mock). Adapters return
 * lead METADATA only — LeadMetadata cannot even represent a name, an email
 * address, a phone number or an answer.
 */
interface LeadSource
{
    /**
     * One page of leads created or changed after $cursor (null = first fetch).
     *
     * @throws AdPlatformException
     */
    public function fetch(LeadSourceModel $source, ?string $cursor): LeadPage;

    /**
     * Cheap reachability/permission probe for the "Test connection" button.
     * Returns a short human-readable confirmation.
     *
     * @throws AdPlatformException
     */
    public function test(LeadSourceModel $source): string;

    public function attempts(): int;

    public function resetAttempts(): void;
}

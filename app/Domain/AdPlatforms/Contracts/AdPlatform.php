<?php

namespace App\Domain\AdPlatforms\Contracts;

use App\Domain\AdPlatforms\Data\AccountInfo;
use App\Domain\AdPlatforms\Data\AdSnapshot;
use App\Domain\AdPlatforms\Data\CampaignDay;
use App\Domain\AdPlatforms\Data\TokenInfo;
use App\Domain\AdPlatforms\Exceptions\AdPlatformException;
use App\Enums\Platform;
use App\Models\AdConnection;
use Carbon\CarbonImmutable;

/**
 * One ad platform, read-only. Adapters are fetch-and-map classes: they talk
 * to the API and return DTOs; persisting is AdDataSync's job.
 *
 * Every method throws AdPlatformException (with a normalised error code) on
 * failure, after the adapter's own retries are exhausted. No method may
 * change anything on the platform — vigilo v1 is strictly read-only.
 */
interface AdPlatform
{
    public function platform(): Platform;

    /** @throws AdPlatformException */
    public function account(AdConnection $connection): AccountInfo;

    /**
     * Campaign-level metrics per day, both dates inclusive, in the account's
     * own timezone.
     *
     * @return list<CampaignDay>
     *
     * @throws AdPlatformException
     */
    public function dailyMetrics(AdConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Ads in enabled campaigns, with review status and final URLs.
     *
     * @return list<AdSnapshot>
     *
     * @throws AdPlatformException
     */
    public function ads(AdConnection $connection): array;

    /**
     * Expiry of the access credential, when the platform has one (Meta).
     * Null when credentials do not expire on a schedule (Google refresh tokens).
     *
     * @throws AdPlatformException
     */
    public function token(AdConnection $connection): ?TokenInfo;

    /** Total HTTP attempts made since the last call to resetAttempts(). */
    public function attempts(): int;

    public function resetAttempts(): void;
}

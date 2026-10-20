<?php

namespace App\Domain\AdPlatforms\Mock;

use App\Enums\Platform;

/**
 * What a simulated connection or lead source does. Picked per connection on
 * the connections page while mock mode is on, and used by the demo seeder.
 */
final class MockScenarios
{
    public const AD_SCENARIOS = [
        'healthy',
        'tracking_broken',
        'conversion_drop',
        'overspend',
        'underspend',
        'cpl_spike',
        'disapproved_ads',
        'limited_ads',
        'broken_landing_page',
        'auth_failed',
        'token_expiring',
        'account_disabled',
    ];

    public const LEAD_SCENARIOS = ['healthy', 'sync_broken', 'auth_failed'];

    /** @return array<string, string> scenario => label */
    public static function adOptions(?Platform $platform = null): array
    {
        $options = [];
        foreach (self::AD_SCENARIOS as $scenario) {
            if ($scenario === 'token_expiring' && $platform === Platform::Google) {
                continue;
            }
            $options[$scenario] = __('mock.'.$scenario);
        }

        return $options;
    }

    /** @return array<string, string> */
    public static function leadOptions(): array
    {
        $options = [];
        foreach (self::LEAD_SCENARIOS as $scenario) {
            $options[$scenario] = __('mock.lead_'.$scenario);
        }

        return $options;
    }
}

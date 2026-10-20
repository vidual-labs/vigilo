<?php

namespace App\Domain\AdPlatforms;

use App\Domain\AdPlatforms\Contracts\AdPlatform;
use App\Domain\AdPlatforms\Google\GoogleAdsPlatform;
use App\Domain\AdPlatforms\Meta\MetaAdsPlatform;
use App\Domain\AdPlatforms\Mock\MockGoogleAdsPlatform;
use App\Domain\AdPlatforms\Mock\MockMetaAdsPlatform;
use App\Enums\Platform;
use Illuminate\Contracts\Container\Container;

/**
 * The one place that maps a platform to its adapter. Adding a platform =
 * adding a Platform case and a row here (plus a mock).
 */
class AdPlatformRegistry
{
    /** @var array<string, array{live: class-string<AdPlatform>, mock: class-string<AdPlatform>}> */
    public const ADAPTERS = [
        'google' => ['live' => GoogleAdsPlatform::class, 'mock' => MockGoogleAdsPlatform::class],
        'meta' => ['live' => MetaAdsPlatform::class, 'mock' => MockMetaAdsPlatform::class],
    ];

    public function __construct(private readonly Container $container) {}

    public function for(Platform $platform): AdPlatform
    {
        $mode = config('vigilo.mock_mode') ? 'mock' : 'live';

        return $this->container->make(self::ADAPTERS[$platform->value][$mode]);
    }
}

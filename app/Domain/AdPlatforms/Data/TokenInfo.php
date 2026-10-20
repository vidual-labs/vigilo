<?php

namespace App\Domain\AdPlatforms\Data;

use Carbon\CarbonImmutable;

final readonly class TokenInfo
{
    /** @param list<string> $scopes */
    public function __construct(
        public bool $isValid,
        /** Null = does not expire. */
        public ?CarbonImmutable $expiresAt,
        public array $scopes = [],
    ) {}
}

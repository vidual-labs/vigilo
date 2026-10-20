<?php

namespace App\Domain\Checks\Snapshot;

use Carbon\CarbonImmutable;

final readonly class ProbeState
{
    /** @param list<array{url: string, status: ?int}> $redirectChain */
    public function __construct(
        public string $url,
        public string $urlHash,
        public string $origin,
        public ?string $finalUrl,
        public ?int $statusCode,
        public array $redirectChain,
        public int $redirectCount,
        public ?int $responseMs,
        public ?CarbonImmutable $tlsExpiresAt,
        public ?string $error,
        public int $consecutiveFailures,
        public ?CarbonImmutable $checkedAt,
    ) {}

    public function failed(): bool
    {
        return $this->error !== null || $this->statusCode === null || $this->statusCode >= 400;
    }
}

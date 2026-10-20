<?php

namespace App\Domain\Checks\LandingPages;

use Carbon\CarbonImmutable;

final readonly class ProbeResult
{
    /** @param list<array{url: string, status: ?int}> $chain every hop including the final one */
    public function __construct(
        public ?string $finalUrl,
        public ?int $statusCode,
        public array $chain,
        public int $redirectCount,
        public ?int $responseMs,
        public ?CarbonImmutable $tlsExpiresAt,
        public ?string $error,
    ) {}

    public function failed(): bool
    {
        return $this->error !== null || $this->statusCode === null || $this->statusCode >= 400;
    }
}

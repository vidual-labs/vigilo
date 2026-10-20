<?php

namespace App\Domain\Checks\Snapshot;

use App\Domain\AdPlatforms\Enums\ConnectionStatus;
use App\Enums\Platform;
use Carbon\CarbonImmutable;

final readonly class ConnectionState
{
    public function __construct(
        public int $id,
        public Platform $platform,
        public string $name,
        public bool $isActive = true,
        public ConnectionStatus $status = ConnectionStatus::Ok,
        public ?CarbonImmutable $lastSuccessAt = null,
        public ?CarbonImmutable $lastAttemptAt = null,
        public ?string $lastErrorCode = null,
        public ?string $lastError = null,
        public ?CarbonImmutable $tokenExpiresAt = null,
        public ?bool $accountActive = null,
        public ?string $accountStatus = null,
        public ?CarbonImmutable $createdAt = null,
    ) {}
}

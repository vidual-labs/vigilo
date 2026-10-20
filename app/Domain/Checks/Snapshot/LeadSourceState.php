<?php

namespace App\Domain\Checks\Snapshot;

use App\Domain\AdPlatforms\Enums\ConnectionStatus;
use Carbon\CarbonImmutable;

final readonly class LeadSourceState
{
    public function __construct(
        public int $id,
        public string $type,
        public ConnectionStatus $status = ConnectionStatus::Ok,
        public ?CarbonImmutable $lastSuccessAt = null,
        public ?CarbonImmutable $lastAttemptAt = null,
        public ?string $lastErrorCode = null,
        public ?string $lastError = null,
        public ?CarbonImmutable $createdAt = null,
    ) {}
}

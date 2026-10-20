<?php

namespace App\Domain\Checks\Snapshot;

use App\Enums\Platform;
use Carbon\CarbonImmutable;

final readonly class LeadFact
{
    public function __construct(
        public CarbonImmutable $submittedAt,
        public bool $countable = true,
        public ?Platform $platform = null,
    ) {}
}

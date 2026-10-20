<?php

namespace App\Domain\Findings\Data;

use App\Domain\Findings\Enums\Severity;
use App\Enums\Platform;

/** What a check reports; FindingReconciler turns drafts into findings. */
final readonly class FindingDraft
{
    /**
     * @param  array<string, mixed>  $titleParams  plain or typed values (see App\Support\Format)
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public string $checkKey,
        public string $dedupeKey,
        public Severity $severity,
        public string $titleKey,
        public array $titleParams = [],
        public array $evidence = [],
        public ?Platform $platform = null,
    ) {}
}

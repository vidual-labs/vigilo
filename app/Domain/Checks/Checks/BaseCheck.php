<?php

namespace App\Domain\Checks\Checks;

use App\Domain\Checks\Contracts\Check;
use App\Domain\Checks\Snapshot\ClientSnapshot;
use App\Domain\Findings\Data\FindingDraft;
use App\Domain\Findings\Enums\Severity;
use App\Enums\Platform;
use App\Support\Format;

abstract class BaseCheck implements Check
{
    /** Dedupe keys are "<check>:<client>:<subject>" — stable across runs. */
    protected function subjectKey(ClientSnapshot $snapshot, string $subject): string
    {
        return $this->key().':'.$snapshot->clientId.':'.$subject;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $evidence
     */
    protected function draft(
        ClientSnapshot $snapshot,
        string $subject,
        Severity $severity,
        string $title,
        array $params = [],
        array $evidence = [],
        ?Platform $platform = null,
    ): FindingDraft {
        return new FindingDraft(
            checkKey: $this->key(),
            dedupeKey: $this->subjectKey($snapshot, $subject),
            severity: $severity,
            titleKey: 'findings.'.$this->key().'.'.$title,
            titleParams: $params,
            evidence: $evidence,
            platform: $platform,
        );
    }

    /** @return array<string, mixed> */
    protected function money(ClientSnapshot $snapshot, float $value): array
    {
        return Format::m($value, $snapshot->currency);
    }
}

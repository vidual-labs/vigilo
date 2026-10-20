<?php

namespace App\Domain\Checks;

use App\Domain\Findings\Data\FindingDraft;

/**
 * Output of one check for one client.
 *
 * `findings` are the problems seen right now. Any open finding of this
 * check that is NOT in the list gets auto-resolved — unless its dedupe key
 * starts with one of the `keep` prefixes, which a check adds when it could
 * not evaluate a subject (stale data, failed fetch). Missing data is not
 * the same as "the problem went away".
 */
final class CheckResult
{
    /** @var list<FindingDraft> */
    public array $findings = [];

    /** @var list<string> */
    public array $keep = [];

    public function add(FindingDraft $draft): self
    {
        $this->findings[] = $draft;

        return $this;
    }

    public function keep(string $dedupePrefix): self
    {
        $this->keep[] = $dedupePrefix;

        return $this;
    }

    public function keeps(string $dedupeKey): bool
    {
        foreach ($this->keep as $prefix) {
            if (str_starts_with($dedupeKey, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Domain\Checks\Contracts;

use App\Domain\Checks\CheckResult;
use App\Domain\Checks\Snapshot\ClientSnapshot;

/**
 * A watchdog check. Checks are pure: they read a ClientSnapshot (built from
 * the database) and return findings — no queries, no HTTP, no side effects —
 * which is what lets every check be unit-tested on fixture data.
 *
 * Defaults for the thresholds live in config/vigilo.php under
 * `checks.<key>`; labels in lang/{en,de}/checks.php.
 */
interface Check
{
    /** Stable key used in config, settings, dedupe keys and translations. */
    public function key(): string;

    /**
     * Threshold fields the settings UI renders, name => type
     * (int | float | ratio | severity | int_list).
     *
     * @return array<string, string>
     */
    public function fields(): array;

    /** @param array<string, mixed> $thresholds resolved: client → install → config */
    public function run(ClientSnapshot $snapshot, array $thresholds): CheckResult;
}

<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/** Decoded JSON fixture from tests/Fixtures. */
function fixture(string $path): array
{
    return json_decode((string) file_get_contents(__DIR__.'/Fixtures/'.$path), true, flags: JSON_THROW_ON_ERROR);
}

/** Thresholds of a check exactly as shipped in config/vigilo.php, with overrides. */
function thresholds(string $check, array $overrides = []): array
{
    $values = config('vigilo.checks.'.$check);
    unset($values['enabled']);

    return array_merge($values, $overrides);
}

/** @return array<string, \App\Domain\Findings\Data\FindingDraft> dedupe suffix => draft */
function findingsBySubject(\App\Domain\Checks\CheckResult $result): array
{
    $out = [];
    foreach ($result->findings as $draft) {
        $out[implode(':', array_slice(explode(':', $draft->dedupeKey), 2))] = $draft;
    }

    return $out;
}

<?php

use App\Domain\Checks\Checks\BudgetPacingCheck;
use App\Domain\Findings\Enums\Severity;
use App\Enums\Platform;
use Tests\Support\SnapshotBuilder;

function pacing(SnapshotBuilder $builder, array $overrides = []): array
{
    return findingsBySubject((new BudgetPacingCheck)->run($builder->build(), thresholds('budget_pacing', $overrides)));
}

it('raises a critical finding when the month is projected far over budget', function () {
    $findings = pacing(SnapshotBuilder::fromFixture('budget_overspend'));

    expect($findings['google']->severity)->toBe(Severity::Critical)
        ->and($findings['google']->titleKey)->toBe('findings.budget_pacing.overpacing')
        ->and($findings['google']->evidence['month_to_date']['value'])->toEqual(2470.0);
});

it('is critical once the whole budget is spent', function () {
    $builder = SnapshotBuilder::at('2026-10-10 08:00')->connection(1)->monthToDate(1, 400)->budget(Platform::Google, 3000);

    expect(pacing($builder)['google']->titleKey)->toBe('findings.budget_pacing.over_budget');
});

it('warns when spending runs a little ahead of plan', function () {
    // 19 days × €110 = €2,090 vs €1,839 planned = 114%… above a 10 % tolerance.
    $builder = SnapshotBuilder::at()->connection(1)->monthToDate(1, 110)->budget(Platform::Google, 3000);

    $findings = pacing($builder, ['tolerance' => 0.1]);

    expect($findings['google']->severity)->toBe(Severity::Warning)
        ->and($findings['google']->titleKey)->toBe('findings.budget_pacing.overpacing');
});

it('warns when spending falls behind plan', function () {
    $builder = SnapshotBuilder::at()->connection(1, Platform::Meta)->monthToDate(1, 30)->budget(Platform::Meta, 2000);

    expect(pacing($builder)['meta']->titleKey)->toBe('findings.budget_pacing.underpacing');
});

it('is quiet on plan', function () {
    $builder = SnapshotBuilder::at()->connection(1)->monthToDate(1, 97)->budget(Platform::Google, 3000);

    expect(pacing($builder))->toBeEmpty();
});

it('waits for a few days of data at the start of the month', function () {
    $builder = SnapshotBuilder::at('2026-10-02 08:00')->connection(1)->monthToDate(1, 300)->budget(Platform::Google, 3000);

    expect(pacing($builder))->toBeEmpty();
});

it('ignores platforms without a budget or without connections', function () {
    $builder = SnapshotBuilder::at()->connection(1)->monthToDate(1, 400)->budget(Platform::Meta, 1000);

    expect(pacing($builder))->toBeEmpty();
});

it('does not judge stale spend', function () {
    $builder = SnapshotBuilder::fromFixture('budget_overspend')->connection(4, Platform::Google, ['last_success_hours_ago' => 50]);
    $result = (new BudgetPacingCheck)->run($builder->build(), thresholds('budget_pacing'));

    expect($result->findings)->toBeEmpty()->and($result->keeps('budget_pacing:7:google'))->toBeTrue();
});

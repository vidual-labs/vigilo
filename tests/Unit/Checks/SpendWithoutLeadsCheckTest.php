<?php

use App\Domain\Checks\Checks\SpendWithoutLeadsCheck;
use App\Domain\Findings\Enums\Severity;
use Tests\Support\SnapshotBuilder;

function spendWithoutLeads(SnapshotBuilder $builder, array $overrides = []): \App\Domain\Checks\CheckResult
{
    return (new SpendWithoutLeadsCheck)->run($builder->build(), thresholds('spend_without_leads', $overrides));
}

it('raises a critical finding when leads stop arriving but spend continues', function () {
    $builder = SnapshotBuilder::at()->connection(1)->series(1, 10, 0, 120, 3)
        ->leadSource()->leadsDaily(10, 3, 3);

    $result = spendWithoutLeads($builder);

    expect($result->findings)->toHaveCount(1)
        ->and($result->findings[0]->severity)->toBe(Severity::Critical)
        ->and($result->findings[0]->dedupeKey)->toBe('spend_without_leads:7:client')
        ->and($result->findings[0]->titleParams['hours'])->toBe(48)
        ->and($result->findings[0]->evidence)->toHaveKey('spend_google');
});

it('is quiet while leads keep coming', function () {
    $builder = SnapshotBuilder::at()->connection(1)->series(1, 10, 0, 120, 3)
        ->leadSource()->leadsDaily(10, 0, 2);

    expect(spendWithoutLeads($builder)->findings)->toBeEmpty();
});

it('counts junk leads as signs of life', function () {
    $builder = SnapshotBuilder::at()->connection(1)->series(1, 10, 0, 120, 3)
        ->leadSource()->lead(SnapshotBuilder::at()->now->subHours(5), countable: false);

    expect(spendWithoutLeads($builder)->findings)->toBeEmpty();
});

it('needs real spend before alerting', function () {
    $builder = SnapshotBuilder::at()->connection(1)->series(1, 10, 0, 5, 0)->leadSource();

    expect(spendWithoutLeads($builder, ['min_spend' => 30])->findings)->toBeEmpty();
});

it('respects the configured number of hours', function () {
    $builder = SnapshotBuilder::at()->connection(1)->series(1, 10, 0, 120, 3)
        ->leadSource()->lead(SnapshotBuilder::at()->now->subHours(30));

    expect(spendWithoutLeads($builder, ['hours' => 48])->findings)->toBeEmpty()
        ->and(spendWithoutLeads($builder, ['hours' => 24])->findings)->toHaveCount(1);
});

it('does nothing without a lead source', function () {
    $builder = SnapshotBuilder::at()->connection(1)->series(1, 10, 0, 120, 3);

    expect(spendWithoutLeads($builder)->findings)->toBeEmpty();
});

it('gives a freshly connected lead source time before alerting', function () {
    $builder = SnapshotBuilder::at()->connection(1)->series(1, 10, 0, 120, 3)->leadSource(['created_hours_ago' => 6]);

    expect(spendWithoutLeads($builder)->findings)->toBeEmpty();
});

it('keeps the finding when the lead source could not be fetched today', function () {
    $builder = SnapshotBuilder::at()->connection(1)->series(1, 10, 0, 120, 3)
        ->leadSource(['last_success_hours_ago' => 40]);

    $result = spendWithoutLeads($builder);

    expect($result->findings)->toBeEmpty()
        ->and($result->keeps('spend_without_leads:7:client'))->toBeTrue();
});

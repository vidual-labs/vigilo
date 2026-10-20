<?php

use App\Domain\Checks\Checks\CplSpikeCheck;
use App\Domain\Findings\Enums\Severity;
use App\Enums\Platform;
use Tests\Support\SnapshotBuilder;

function cplSpike(SnapshotBuilder $builder, array $overrides = []): array
{
    return findingsBySubject((new CplSpikeCheck)->run($builder->build(), thresholds('cpl_spike', $overrides)));
}

it('flags a doubled cost per lead from platform conversions', function () {
    $findings = cplSpike(SnapshotBuilder::fromFixture('cpl_spike_meta'));

    expect($findings['meta']->severity)->toBe(Severity::Critical)
        ->and($findings['meta']->titleParams['factor']['value'])->toEqual(2.5)
        ->and($findings['meta']->evidence['basis']['value'])->toBe('evidence.basis_platform_conversions');
});

it('warns on a smaller increase', function () {
    $builder = SnapshotBuilder::at()->connection(1)->series(1, 40, 8, 100, 4)->series(1, 7, 1, 100, 2.5);

    expect(cplSpike($builder)['google']->severity)->toBe(Severity::Warning);
});

it('prefers attributed leads from the lead source', function () {
    // Platform conversions look fine, but real Google leads halved.
    $builder = SnapshotBuilder::at()->connection(1)->series(1, 40, 8, 100, 4)->series(1, 7, 1, 100, 4)
        ->leadSource()
        ->leadsDaily(35, 8, 4, Platform::Google)
        ->leadsDaily(7, 1, 1, Platform::Google)
        ->leadsDaily(7, 1, 1, Platform::Google)
        ->lead(SnapshotBuilder::at()->now->subHours(3), Platform::Google);

    $findings = cplSpike($builder);

    expect($findings['google']->severity)->toBe(Severity::Critical)
        ->and($findings['google']->evidence['basis']['value'])->toBe('evidence.basis_lead_source');
});

it('falls back to platform conversions when the lead source went silent', function () {
    $builder = SnapshotBuilder::at()->connection(1)->series(1, 40, 1, 100, 4)
        ->leadSource()->leadsDaily(35, 4, 4, Platform::Google);

    expect(cplSpike($builder))->toBeEmpty();
});

it('needs enough spend and baseline leads', function () {
    $low = SnapshotBuilder::at()->connection(1)->series(1, 40, 8, 10, 4)->series(1, 7, 1, 10, 1);
    $thin = SnapshotBuilder::at()->connection(1)->series(1, 40, 8, 100, 0)->series(1, 30, 30, 100, 2)->series(1, 7, 1, 100, 1);

    expect(cplSpike($low))->toBeEmpty()->and(cplSpike($thin))->toBeEmpty();
});

it('leaves zero recent leads to the conversion-drop check', function () {
    $builder = SnapshotBuilder::at()->connection(1)->series(1, 40, 8, 100, 4)->series(1, 7, 1, 100, 0);

    expect(cplSpike($builder))->toBeEmpty();
});

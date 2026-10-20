<?php

use App\Domain\Checks\CheckResult;
use App\Domain\Checks\Checks\ConversionDropCheck;
use App\Domain\Findings\Enums\Severity;
use App\Enums\Platform;
use Tests\Support\SnapshotBuilder;

function conversionDrop(SnapshotBuilder $builder, array $overrides = []): CheckResult
{
    return (new ConversionDropCheck)->run($builder->build(), thresholds('conversion_drop', $overrides));
}

it('raises a critical finding when tracking stops while spend continues', function () {
    $result = conversionDrop(SnapshotBuilder::fromFixture('conversion_tracking_broken'));

    expect($result->findings)->toHaveCount(1);
    $finding = $result->findings[0];
    expect($finding->severity)->toBe(Severity::Critical)
        ->and($finding->titleKey)->toBe('findings.conversion_drop.zero')
        ->and($finding->dedupeKey)->toBe('conversion_drop:7:conn:1')
        ->and($finding->platform)->toBe(Platform::Google)
        ->and($finding->titleParams['days'])->toBe(1)
        ->and($finding->evidence['conversions']['value'])->toEqual(0.0);
});

it('warns when conversions drop well below the baseline', function () {
    $result = conversionDrop(SnapshotBuilder::fromFixture('conversion_soft_drop'));

    expect($result->findings)->toHaveCount(1)
        ->and($result->findings[0]->severity)->toBe(Severity::Warning)
        ->and($result->findings[0]->titleKey)->toBe('findings.conversion_drop.drop')
        ->and($result->findings[0]->titleParams['ratio']['value'])->toBeLessThan(0.5);
});

it('stays quiet for a healthy account', function () {
    $builder = SnapshotBuilder::at()->connection(1)->series(1, 40, 1, 150, 4);

    expect(conversionDrop($builder)->findings)->toBeEmpty();
});

it('ignores windows without meaningful spend', function () {
    $builder = SnapshotBuilder::at()->connection(1)->series(1, 40, 4, 150, 4)->series(1, 3, 1, 5, 0);

    expect(conversionDrop($builder)->findings)->toBeEmpty();
});

it('ignores accounts whose baseline is too thin to judge', function () {
    $builder = SnapshotBuilder::at()->connection(1)->series(1, 40, 4, 150, 0)->series(1, 30, 30, 150, 3)->series(1, 3, 1, 150, 0);

    expect(conversionDrop($builder)->findings)->toBeEmpty();
});

it('uses only the 3-day window for low-volume accounts', function () {
    // ~0.86 conversions a day: one quiet day is normal, three are not.
    $builder = SnapshotBuilder::at()->connection(1);
    for ($ago = 40; $ago >= 4; $ago--) {
        $builder->series(1, $ago, $ago, 80, $ago % 7 === 0 ? 0 : 1);
    }
    $builder->series(1, 3, 1, 80, 0);

    $result = conversionDrop($builder);

    expect($result->findings)->toHaveCount(1)
        ->and($result->findings[0]->titleParams['days'])->toBe(3);
});

it('does not judge stale data and keeps any open finding', function () {
    $builder = SnapshotBuilder::fromFixture('conversion_tracking_broken')
        ->connection(1, Platform::Google, ['last_success_hours_ago' => 30]);

    $result = conversionDrop($builder);

    expect($result->findings)->toBeEmpty()
        ->and($result->keeps('conversion_drop:7:conn:1'))->toBeTrue();
});

it('skips paused connections', function () {
    $builder = SnapshotBuilder::fromFixture('conversion_tracking_broken')->connection(1, Platform::Google, ['is_active' => false]);

    expect(conversionDrop($builder)->findings)->toBeEmpty();
});

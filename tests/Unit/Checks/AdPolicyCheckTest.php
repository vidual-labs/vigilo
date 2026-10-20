<?php

use App\Domain\AdPlatforms\Enums\ReviewStatus;
use App\Domain\Checks\Checks\AdPolicyCheck;
use App\Domain\Findings\Enums\Severity;
use Tests\Support\SnapshotBuilder;

function adPolicy(SnapshotBuilder $builder, array $overrides = []): array
{
    return findingsBySubject((new AdPolicyCheck)->run($builder->build(), thresholds('ad_policy', $overrides)));
}

it('warns about disapproved ads and lists them', function () {
    $builder = SnapshotBuilder::at()->connection(1)
        ->ad(1, 'a1', 'c1', ReviewStatus::Disapproved, topics: ['Misleading claims'])
        ->ad(1, 'a2', 'c1', ReviewStatus::Approved);

    $findings = adPolicy($builder);

    expect($findings['conn:1:disapproved']->severity)->toBe(Severity::Warning)
        ->and($findings['conn:1:disapproved']->titleParams['count'])->toBe(1)
        ->and($findings['conn:1:disapproved']->evidence['ads'][0]['reason'])->toBe('Misleading claims');
});

it('is critical when a campaign has no approved ad left', function () {
    $builder = SnapshotBuilder::at()->connection(1)
        ->ad(1, 'a1', 'c1', ReviewStatus::Disapproved)
        ->ad(1, 'a2', 'c1', ReviewStatus::Disapproved)
        ->ad(1, 'a3', 'c2', ReviewStatus::Approved);

    $findings = adPolicy($builder);

    expect($findings['conn:1:disapproved']->severity)->toBe(Severity::Critical)
        ->and($findings['conn:1:disapproved']->titleKey)->toBe('findings.ad_policy.campaign_blocked')
        ->and($findings['conn:1:disapproved']->evidence['blocked_campaigns'])->toBe(['Campaign c1']);
});

it('reports limited ads with the configured severity', function () {
    $builder = SnapshotBuilder::at()->connection(1)->ad(1, 'a1', 'c1', ReviewStatus::Limited);

    expect(adPolicy($builder)['conn:1:limited']->severity)->toBe(Severity::Info)
        ->and(adPolicy($builder, ['limited_severity' => 'warning'])['conn:1:limited']->severity)->toBe(Severity::Warning);
});

it('ignores paused ads and approved ones', function () {
    $builder = SnapshotBuilder::at()->connection(1)
        ->ad(1, 'a1', 'c1', ReviewStatus::Disapproved, active: false)
        ->ad(1, 'a2', 'c1', ReviewStatus::Approved);

    expect(adPolicy($builder))->toBeEmpty();
});

it('keeps findings when ads could not be fetched today', function () {
    $builder = SnapshotBuilder::at()->connection(1, overrides: ['last_success_hours_ago' => 26])->ad(1, 'a1', 'c1', ReviewStatus::Disapproved);
    $result = (new AdPolicyCheck)->run($builder->build(), thresholds('ad_policy'));

    expect($result->findings)->toBeEmpty()->and($result->keeps('ad_policy:7:conn:1:disapproved'))->toBeTrue();
});

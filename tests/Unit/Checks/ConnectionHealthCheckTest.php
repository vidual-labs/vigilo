<?php

use App\Domain\Checks\Checks\ConnectionHealthCheck;
use App\Domain\Findings\Enums\Severity;
use App\Enums\Platform;
use Tests\Support\SnapshotBuilder;

function connectionHealth(SnapshotBuilder $builder): array
{
    return findingsBySubject((new ConnectionHealthCheck)->run($builder->build(), thresholds('connection_health')));
}

it('reports failed authentication as critical', function () {
    $findings = connectionHealth(SnapshotBuilder::at()->connection(1, Platform::Meta, [
        'status' => 'auth_failed', 'last_error' => 'Error validating access token', 'last_success_hours_ago' => 20,
    ]));

    expect($findings['conn:1']->severity)->toBe(Severity::Critical)
        ->and($findings['conn:1']->titleKey)->toBe('findings.connection_health.auth_failed')
        ->and($findings['conn:1']->evidence['last_error'])->toBe('Error validating access token');
});

it('warns when data gets stale and escalates when it stays stale', function () {
    $warning = connectionHealth(SnapshotBuilder::at()->connection(1, Platform::Google, ['status' => 'failing', 'last_success_hours_ago' => 30]));
    $critical = connectionHealth(SnapshotBuilder::at()->connection(1, Platform::Google, ['status' => 'failing', 'last_success_hours_ago' => 80]));

    expect($warning['conn:1']->severity)->toBe(Severity::Warning)
        ->and($warning['conn:1']->titleParams['hours'])->toBe(30)
        ->and($critical['conn:1']->severity)->toBe(Severity::Critical);
});

it('flags a connection that never succeeded once it is old enough', function () {
    $findings = connectionHealth(SnapshotBuilder::at()->connection(1, Platform::Google, ['last_success_hours_ago' => null, 'created_hours_ago' => 30]));

    expect($findings['conn:1']->titleKey)->toBe('findings.connection_health.never_fetched');
});

it('leaves a brand-new connection alone', function () {
    expect(connectionHealth(SnapshotBuilder::at()->connection(1, Platform::Google, ['last_success_hours_ago' => null, 'created_hours_ago' => 2])))->toBeEmpty();
});

it('warns about Meta tokens expiring within 14 days and escalates in the last 3', function (int $days, ?Severity $expected, string $title) {
    $findings = connectionHealth(SnapshotBuilder::at()->connection(1, Platform::Meta, ['token_expires_in_days' => $days]));

    if ($expected === null) {
        expect($findings)->not->toHaveKey('conn:1:token');
    } else {
        expect($findings['conn:1:token']->severity)->toBe($expected)
            ->and($findings['conn:1:token']->titleKey)->toBe('findings.connection_health.'.$title);
    }
})->with([
    'far away' => [40, null, ''],
    'two weeks' => [14, Severity::Warning, 'token_expiring'],
    'five days' => [5, Severity::Warning, 'token_expiring'],
    'three days' => [3, Severity::Critical, 'token_expiring'],
    'expired' => [-1, Severity::Critical, 'token_expired'],
]);

it('reports a disabled ad account as critical', function () {
    $findings = connectionHealth(SnapshotBuilder::at()->connection(1, Platform::Meta, ['account_active' => false, 'account_status' => 'UNSETTLED']));

    expect($findings['conn:1:account']->severity)->toBe(Severity::Critical)
        ->and($findings['conn:1:account']->titleParams['status'])->toBe('UNSETTLED');
});

it('watches the lead source too', function () {
    $findings = connectionHealth(SnapshotBuilder::at()->leadSource(['id' => 9, 'status' => 'auth_failed']));

    expect($findings['leads:9']->severity)->toBe(Severity::Critical);
});

it('is silent for healthy connections', function () {
    expect(connectionHealth(SnapshotBuilder::at()->connection(1)->connection(2, Platform::Meta)->leadSource()))->toBeEmpty();
});

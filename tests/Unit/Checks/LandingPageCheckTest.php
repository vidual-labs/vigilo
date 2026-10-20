<?php

use App\Domain\Checks\Checks\LandingPageCheck;
use App\Domain\Findings\Enums\Severity;
use Tests\Support\SnapshotBuilder;

function landing(SnapshotBuilder $builder): \App\Domain\Checks\CheckResult
{
    return (new LandingPageCheck)->run($builder->build(), thresholds('landing_page'));
}

it('raises a critical finding for a page that keeps failing', function () {
    $url = 'https://www.example.com/angebot';
    $result = landing(SnapshotBuilder::at()->probe($url, ['status' => 404, 'failures' => 2]));
    $findings = findingsBySubject($result);

    expect($findings['url:'.sha1($url).':down']->severity)->toBe(Severity::Critical)
        ->and($findings['url:'.sha1($url).':down']->titleParams)->toMatchArray(['url' => 'example.com/angebot', 'status' => '404']);
});

it('waits for a second failure before alerting, without resolving', function () {
    $url = 'https://www.example.com/x';
    $result = landing(SnapshotBuilder::at()->probe($url, ['status' => null, 'error' => 'cURL 28: timeout', 'failures' => 1]));

    expect($result->findings)->toBeEmpty()
        ->and($result->keeps('landing_page:7:url:'.sha1($url).':down'))->toBeTrue();
});

it('warns about long redirect chains, off-site redirects and HTTPS downgrades', function () {
    $url = 'https://www.example.com/webinar';
    $findings = findingsBySubject(landing(SnapshotBuilder::at()->probe($url, [
        'redirects' => 3, 'final_url' => 'http://events.other-site.com/register',
    ])));
    $hash = sha1($url);

    expect($findings)->toHaveKeys(["url:{$hash}:redirects", "url:{$hash}:offsite", "url:{$hash}:insecure"])
        ->and($findings["url:{$hash}:redirects"]->severity)->toBe(Severity::Warning);
});

it('treats www and the bare domain as the same site', function () {
    $findings = findingsBySubject(landing(SnapshotBuilder::at()->probe('https://example.com/', ['final_url' => 'https://www.example.com/'])));

    expect($findings)->toBeEmpty();
});

it('warns about slow pages', function () {
    $url = 'https://www.example.com/rechner';
    $findings = findingsBySubject(landing(SnapshotBuilder::at()->probe($url, ['ms' => 4200])));

    expect($findings['url:'.sha1($url).':slow']->titleParams['seconds']['value'])->toEqual(4.2);
});

it('watches TLS expiry once per host', function (int $days, Severity $severity, string $title) {
    $findings = findingsBySubject(landing(SnapshotBuilder::at()
        ->probe('https://termine.example.com/a', ['tls_days' => $days])
        ->probe('https://termine.example.com/b', ['tls_days' => $days])));

    expect($findings)->toHaveCount(1)
        ->and($findings['tls:termine.example.com']->severity)->toBe($severity)
        ->and($findings['tls:termine.example.com']->titleKey)->toBe('findings.landing_page.'.$title);
})->with([
    'in 10 days' => [10, Severity::Warning, 'tls_expiring'],
    'in 2 days' => [2, Severity::Critical, 'tls_expiring'],
    'expired' => [-1, Severity::Critical, 'tls_expired'],
]);

it('ignores healthy pages and probes that are no longer current', function () {
    $result = landing(SnapshotBuilder::at()
        ->probe('https://www.example.com/', ['tls_days' => 80])
        ->probe('https://www.example.com/old', ['status' => 404, 'failures' => 5, 'checked_hours_ago' => 72]));

    expect($result->findings)->toBeEmpty();
});

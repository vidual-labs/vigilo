<?php

use App\Domain\AdPlatforms\Enums\ReviewStatus;
use App\Domain\AdPlatforms\Google\GoogleAdsPlatform;
use App\Domain\AdPlatforms\Meta\MetaAdsPlatform;
use App\Domain\Checks\LandingPages\HttpUrlProber;
use App\Domain\Checks\LandingPages\LandingPageProbeService;

it('picks the first configured Meta conversion action', function () {
    $actions = [
        ['action_type' => 'link_click', 'value' => '40'],
        ['action_type' => 'offsite_conversion.fb_pixel_lead', 'value' => '3'],
        ['action_type' => 'lead', 'value' => '5'],
    ];

    expect(MetaAdsPlatform::conversions($actions, ['lead', 'offsite_conversion.fb_pixel_lead']))->toBe(5.0)
        ->and(MetaAdsPlatform::conversions($actions, ['onsite_conversion.lead_grouped']))->toBe(0.0);
});

it('extracts landing pages from Meta creatives and skips Facebook-hosted forms', function () {
    $urls = MetaAdsPlatform::finalUrls([
        'object_story_spec' => ['link_data' => ['link' => 'http://fb.me/', 'call_to_action' => ['value' => ['link' => 'https://www.example.com/angebot']]]],
        'asset_feed_spec' => ['link_urls' => [['website_url' => 'https://www.example.com/angebot'], ['website_url' => 'https://www.example.com/b']]],
    ]);

    expect($urls)->toBe(['https://www.example.com/angebot', 'https://www.example.com/b']);
});

it('normalises review statuses', function () {
    expect(GoogleAdsPlatform::reviewStatus('APPROVED_LIMITED', null))->toBe(ReviewStatus::Limited)
        ->and(GoogleAdsPlatform::reviewStatus('DISAPPROVED', null))->toBe(ReviewStatus::Disapproved)
        ->and(GoogleAdsPlatform::reviewStatus('UNKNOWN', 'REVIEW_IN_PROGRESS'))->toBe(ReviewStatus::UnderReview)
        ->and(MetaAdsPlatform::reviewStatus('WITH_ISSUES'))->toBe(ReviewStatus::Limited)
        ->and(MetaAdsPlatform::reviewStatus('ACTIVE'))->toBe(ReviewStatus::Approved);
});

it('normalises landing page URLs before probing', function () {
    expect(LandingPageProbeService::normalize('HTTPS://Www.Example.com/angebot?utm_source=google&gclid=abc&b=2&a=1#form'))
        ->toBe('https://www.example.com/angebot?a=1&b=2')
        ->and(LandingPageProbeService::normalize('{lpurl}?x=1'))->toBeNull()
        ->and(LandingPageProbeService::normalize('mailto:x@example.com'))->toBeNull()
        ->and(LandingPageProbeService::normalize('https://example.com'))->toBe('https://example.com/');
});

it('resolves relative redirect targets', function () {
    expect(HttpUrlProber::resolve('https://a.example/x/y', '/z'))->toBe('https://a.example/z')
        ->and(HttpUrlProber::resolve('https://a.example/x/y', 'z'))->toBe('https://a.example/x/z')
        ->and(HttpUrlProber::resolve('https://a.example/x', '//b.example/'))->toBe('https://b.example/')
        ->and(HttpUrlProber::resolve('https://a.example/x', 'https://c.example/'))->toBe('https://c.example/');
});

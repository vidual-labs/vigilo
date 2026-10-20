<?php

use App\Domain\AdPlatforms\Enums\ReviewStatus;
use App\Domain\AdPlatforms\Exceptions\AdPlatformException;
use App\Domain\AdPlatforms\Google\GoogleAdsPlatform;
use App\Models\AdConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake();
    config([
        'vigilo.mock_mode' => false,
        'vigilo.google_ads.developer_token' => 'dev-token',
        'vigilo.google_ads.client_id' => 'client-id.apps.googleusercontent.com',
        'vigilo.google_ads.client_secret' => 'client-secret',
        'vigilo.google_ads.refresh_token' => '1//agency-refresh',
        'vigilo.google_ads.login_customer_id' => '555-000-1234',
    ]);
    $this->connection = AdConnection::factory()->create(['account_id' => '1234567890']);
    $this->google = app(GoogleAdsPlatform::class);
});

function googleUrl(string $customer = '1234567890'): string
{
    return 'https://googleads.googleapis.com/v26/customers/'.$customer.'/googleAds:search';
}

it('authenticates with the refresh token and reads the account', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(fixture('google/oauth_token.json')),
        googleUrl() => Http::response(fixture('google/customer.json')),
    ]);

    $account = $this->google->account($this->connection);

    expect($account->name)->toBe('Alpenblick Solar GmbH')
        ->and($account->currency)->toBe('EUR')
        ->and($account->isActive)->toBeTrue();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'oauth2.googleapis.com')
        && $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === '1//agency-refresh');
    Http::assertSent(fn (Request $r) => $r->url() === googleUrl()
        && $r->hasHeader('Authorization', 'Bearer ya29.test-access-token')
        && $r->hasHeader('developer-token', 'dev-token')
        && $r->hasHeader('login-customer-id', '5550001234')
        && str_contains($r['query'], 'FROM customer'));
});

it('uses a per-connection refresh token override and login customer id', function () {
    $this->connection->update(['credentials' => ['refresh_token' => '1//client-own'], 'login_customer_id' => '9998887777']);
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(fixture('google/oauth_token.json')),
        googleUrl() => Http::response(fixture('google/customer.json')),
    ]);

    $this->google->account($this->connection->fresh());

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'oauth2') && $r['refresh_token'] === '1//client-own');
    Http::assertSent(fn (Request $r) => $r->hasHeader('login-customer-id', '9998887777'));
});

it('pages through GAQL results and maps campaign metrics', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(fixture('google/oauth_token.json')),
        googleUrl() => Http::sequence()
            ->push(fixture('google/metrics_page1.json'))
            ->push(fixture('google/metrics_page2.json')),
    ]);

    $days = $this->google->dailyMetrics($this->connection, CarbonImmutable::parse('2026-10-18'), CarbonImmutable::parse('2026-10-19'));

    expect($days)->toHaveCount(3)
        ->and($days[0]->spend)->toBe(41.23)
        ->and($days[1]->conversions)->toBe(2.5)
        ->and($days[2]->date)->toBe('2026-10-19');
    Http::assertSent(fn (Request $r) => $r->url() === googleUrl() && ($r['pageToken'] ?? null) === 'page-2'
        && str_contains($r['query'], "BETWEEN '2026-10-18' AND '2026-10-19'"));
});

it('maps ads with policy status and Performance Max landing pages', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(fixture('google/oauth_token.json')),
        googleUrl() => Http::sequence()->push(fixture('google/ads.json'))->push(fixture('google/asset_groups.json')),
    ]);

    $ads = $this->google->ads($this->connection);

    expect($ads)->toHaveCount(3)
        ->and($ads[0]->reviewStatus)->toBe(ReviewStatus::Disapproved)
        ->and($ads[0]->policyTopics)->toBe(['MISLEADING_CLAIMS (PROHIBITED)'])
        ->and($ads[0]->finalUrls)->toBe(['https://www.alpenblick-solar.de/gewerbe?utm_source=google'])
        ->and($ads[1]->reviewStatus)->toBe(ReviewStatus::Approved)
        ->and($ads[2]->externalId)->toBe('asset_group:888')
        ->and($ads[2]->finalUrls)->toBe(['https://www.alpenblick-solar.de/kontakt']);
});

it('reports permission problems as auth errors without retrying', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(fixture('google/oauth_token.json')),
        googleUrl() => Http::response(fixture('google/error_permission_denied.json'), 403),
    ]);

    try {
        $this->google->account($this->connection);
        $this->fail('expected an exception');
    } catch (AdPlatformException $e) {
        expect($e->errorCode)->toBe(AdPlatformException::AUTH)
            ->and($e->httpStatus)->toBe(403)
            ->and($e->getMessage())->toContain('USER_PERMISSION_DENIED');
    }
    Http::assertSentCount(2);
});

it('retries rate limits and server errors with backoff', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(fixture('google/oauth_token.json')),
        googleUrl() => Http::sequence()
            ->push(fixture('google/error_quota.json'), 429, ['Retry-After' => '5'])
            ->push(['error' => ['code' => 503, 'status' => 'UNAVAILABLE']], 503)
            ->push(fixture('google/customer.json')),
    ]);

    $this->google->resetAttempts();
    $account = $this->google->account($this->connection);

    expect($account->name)->toBe('Alpenblick Solar GmbH')
        ->and($this->google->attempts())->toBe(4); // token + 3 search attempts
    Sleep::assertSequence([Sleep::for(5)->seconds(), Sleep::for(3000)->milliseconds()]);
});

it('treats a revoked refresh token as an auth failure', function () {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400)]);

    expect(fn () => $this->google->account($this->connection))
        ->toThrow(fn (AdPlatformException $e) => expect($e->errorCode)->toBe(AdPlatformException::AUTH));
});

it('refuses to run without credentials', function () {
    config(['vigilo.google_ads.developer_token' => '']);
    Http::fake();

    expect(fn () => $this->google->account($this->connection))
        ->toThrow(fn (AdPlatformException $e) => expect($e->errorCode)->toBe(AdPlatformException::CONFIG));
    Http::assertNothingSent();
});

it('never issues a mutate call', function () {
    $source = file_get_contents(app_path('Domain/AdPlatforms/Google/GoogleAdsClient.php')).file_get_contents(app_path('Domain/AdPlatforms/Google/GoogleAdsPlatform.php'));

    expect($source)->not->toContain(':mutate')->not->toContain('Mutate');
});

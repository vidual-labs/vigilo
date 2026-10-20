<?php

use App\Domain\AdPlatforms\Enums\ReviewStatus;
use App\Domain\AdPlatforms\Exceptions\AdPlatformException;
use App\Domain\AdPlatforms\Meta\MetaAdsPlatform;
use App\Models\AdConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake();
    config([
        'vigilo.mock_mode' => false,
        'vigilo.meta.access_token' => 'EAAB-system-user',
        'vigilo.meta.app_secret' => 'app-secret',
    ]);
    $this->connection = AdConnection::factory()->meta()->create(['account_id' => 'act_120400001']);
    $this->meta = app(MetaAdsPlatform::class);
});

it('reads the ad account with an appsecret_proof', function () {
    Http::fake(['graph.facebook.com/v26.0/act_120400001*' => Http::response(fixture('meta/account.json'))]);

    $account = $this->meta->account($this->connection);

    expect($account->name)->toBe('Nordwerk Maschinenbau AG')->and($account->isActive)->toBeTrue();
    Http::assertSent(fn (Request $r) => $r['access_token'] === 'EAAB-system-user'
        && $r['appsecret_proof'] === hash_hmac('sha256', 'EAAB-system-user', 'app-secret'));
});

it('flags a disabled ad account', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['name' => 'X', 'account_status' => 3])]);

    $account = $this->meta->account($this->connection);

    expect($account->isActive)->toBeFalse()->and($account->rawStatus)->toBe('UNSETTLED');
});

it('pages through insights with cursors and counts lead actions', function () {
    Http::fake(['graph.facebook.com/v26.0/act_120400001/insights*' => Http::sequence()
        ->push(fixture('meta/insights_page1.json'))
        ->push(fixture('meta/insights_page2.json'))]);

    $days = $this->meta->dailyMetrics($this->connection, CarbonImmutable::parse('2026-10-18'), CarbonImmutable::parse('2026-10-18'));

    expect($days)->toHaveCount(2)
        ->and($days[0]->spend)->toBe(84.37)
        ->and($days[0]->conversions)->toBe(4.0)
        ->and($days[1]->conversions)->toBe(2.0);
    Http::assertSent(fn (Request $r) => ($r['after'] ?? null) === 'MQZDZD' && $r['level'] === 'campaign' && $r['time_increment'] === 1);
});

it('maps ads, review feedback and landing pages', function () {
    Http::fake(['graph.facebook.com/v26.0/act_120400001/ads*' => Http::response(fixture('meta/ads.json'))]);

    $ads = $this->meta->ads($this->connection);

    expect($ads)->toHaveCount(3)
        ->and($ads[0]->reviewStatus)->toBe(ReviewStatus::Disapproved)
        ->and($ads[0]->policyTopics)->toBe(['Personal Attributes'])
        ->and($ads[0]->finalUrls)->toBe(['https://www.nordwerk.de/angebot?utm_source=meta'])
        ->and($ads[0]->isActive)->toBeTrue()
        ->and($ads[1]->finalUrls)->toBe([])
        ->and($ads[2]->isActive)->toBeFalse();
});

it('reads token expiry from debug_token', function () {
    Http::fake(['graph.facebook.com/v26.0/debug_token*' => Http::response(fixture('meta/debug_token.json'))]);

    $token = $this->meta->token($this->connection);

    expect($token->expiresAt?->timestamp)->toBe(1792000000)->and($token->scopes)->toContain('ads_read');
});

it('treats an expired token as an auth failure', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(fixture('meta/error_token.json'), 400)]);

    expect(fn () => $this->meta->account($this->connection))
        ->toThrow(fn (AdPlatformException $e) => expect($e->errorCode)->toBe(AdPlatformException::AUTH)->and($e->getMessage())->toContain('code 190'));
});

it('backs off when Meta throttles, honouring the usage header', function () {
    Http::fake(['graph.facebook.com/*' => Http::sequence()
        ->push(fixture('meta/error_throttled.json'), 400, ['X-Business-Use-Case-Usage' => json_encode(['120400001' => [['type' => 'ads_insights', 'estimated_time_to_regain_access' => 0]]])])
        ->push(fixture('meta/account.json'))]);

    expect($this->meta->account($this->connection)->name)->toBe('Nordwerk Maschinenbau AG');
    Http::assertSentCount(2);
});

it('gives up when the throttling window is longer than we are willing to wait', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(fixture('meta/error_throttled.json'), 400, [
        'X-Business-Use-Case-Usage' => json_encode(['1' => [['estimated_time_to_regain_access' => 30]]]),
    ])]);

    expect(fn () => $this->meta->account($this->connection))
        ->toThrow(fn (AdPlatformException $e) => expect($e->errorCode)->toBe(AdPlatformException::RATE_LIMITED));
    Http::assertSentCount(1);
});

it('only ever sends GET requests to Meta', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(fixture('meta/account.json'))]);
    $this->meta->account($this->connection);

    Http::assertSent(fn (Request $r) => $r->method() === 'GET');
    Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
});

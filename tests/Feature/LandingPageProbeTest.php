<?php

use App\Domain\Checks\LandingPages\HttpUrlProber;
use App\Domain\Checks\LandingPages\LandingPageProbeService;
use App\Domain\Checks\LandingPages\TlsInspector;
use App\Models\Ad;
use App\Models\AdConnection;
use App\Models\Client;
use App\Models\UrlProbe;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->mock(TlsInspector::class)->shouldReceive('expiry')->andReturn(CarbonImmutable::now()->addDays(9));
});

it('follows redirects hop by hop with GET only and reads the certificate', function () {
    Http::fake([
        'https://example.com/webinar' => Http::response('', 301, ['Location' => '/events/webinar']),
        'https://example.com/events/webinar' => Http::response('', 302, ['Location' => 'https://www.example.com/events/webinar/']),
        'https://www.example.com/events/webinar/' => Http::response('<html>ok</html>', 200),
    ]);

    $result = app(HttpUrlProber::class)->probe('https://example.com/webinar');

    expect($result->statusCode)->toBe(200)
        ->and($result->redirectCount)->toBe(2)
        ->and($result->finalUrl)->toBe('https://www.example.com/events/webinar/')
        ->and($result->tlsExpiresAt)->not->toBeNull();
    Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
});

it('reports unreachable pages', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('cURL error 6: Could not resolve host: nope.example (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)')]);

    $result = app(HttpUrlProber::class)->probe('https://nope.example/');

    expect($result->failed())->toBeTrue()->and($result->error)->toBe('cURL 6: Could not resolve host: nope.example');
});

it('probes monitored URLs and ad landing pages once each and counts failures in a row', function () {
    config(['vigilo.mock_mode' => false]);
    Http::fake([
        'https://www.acme.example/' => Http::response('ok'),
        'https://www.acme.example/old' => Http::response('gone', 404),
    ]);
    $client = Client::factory()->create();
    $client->monitoredUrls()->create(['url' => 'https://www.acme.example/old']);
    $connection = AdConnection::factory()->for($client)->create();
    Ad::query()->create([
        'ad_connection_id' => $connection->id, 'client_id' => $client->id, 'platform' => 'google', 'external_id' => '1',
        'is_active' => true, 'review_status' => 'approved', 'final_urls' => ['https://www.acme.example/?utm_source=google', 'https://www.acme.example/?gclid=x'],
    ]);

    $service = app(LandingPageProbeService::class);
    $service->probeClient($client);
    $service->probeClient($client);

    expect(UrlProbe::query()->count())->toBe(2)
        ->and(UrlProbe::query()->where('url', 'https://www.acme.example/old')->value('consecutive_failures'))->toBe(2)
        ->and(UrlProbe::query()->where('url', 'https://www.acme.example/')->value('consecutive_failures'))->toBe(0);
    Http::assertSentCount(4);
});

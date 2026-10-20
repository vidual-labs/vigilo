<?php

use App\Domain\AdPlatforms\Enums\ConnectionStatus;
use App\Domain\AdPlatforms\Services\AdDataSync;
use App\Models\Ad;
use App\Models\AdConnection;
use App\Models\AdMetricDaily;
use App\Models\FetchLog;

it('stores metrics, ads and account info from the adapter and logs every fetch', function () {
    $connection = AdConnection::factory()->create();

    $result = app(AdDataSync::class)->sync($connection);

    $connection->refresh();
    expect($result['ok'])->toBeTrue()
        ->and($connection->status)->toBe(ConnectionStatus::Ok)
        ->and($connection->last_success_at)->not->toBeNull()
        ->and($connection->setting('account_name'))->toContain('Google Ads')
        ->and(AdMetricDaily::query()->where('ad_connection_id', $connection->id)->count())->toBeGreaterThan(100)
        ->and(Ad::query()->where('ad_connection_id', $connection->id)->count())->toBe(6)
        ->and(FetchLog::query()->pluck('kind')->all())->toBe(['account', 'metrics', 'ads', 'token'])
        ->and(FetchLog::query()->where('status', 'success')->count())->toBe(4);
});

it('replaces the fetched window instead of duplicating rows', function () {
    $connection = AdConnection::factory()->create();
    app(AdDataSync::class)->sync($connection);
    $count = AdMetricDaily::query()->count();

    app(AdDataSync::class)->sync($connection);

    expect(AdMetricDaily::query()->count())->toBe($count);
});

it('records an authentication failure and stops hitting the API', function () {
    $connection = AdConnection::factory()->scenario('auth_failed')->create();

    $result = app(AdDataSync::class)->sync($connection);

    $connection->refresh();
    expect($result['ok'])->toBeFalse()
        ->and($result['error_code'])->toBe('auth')
        ->and($connection->status)->toBe(ConnectionStatus::AuthFailed)
        ->and($connection->consecutive_failures)->toBe(1)
        ->and(FetchLog::query()->count())->toBe(1)
        ->and(FetchLog::query()->first()->status)->toBe('failed');
});

it('tests a connection without fetching data', function () {
    $connection = AdConnection::factory()->meta()->scenario('token_expiring')->create();

    $result = app(AdDataSync::class)->test($connection);

    expect($result['ok'])->toBeTrue()
        ->and($connection->fresh()->token_expires_at)->not->toBeNull()
        ->and(AdMetricDaily::query()->count())->toBe(0)
        ->and(FetchLog::query()->value('kind'))->toBe('test');
});

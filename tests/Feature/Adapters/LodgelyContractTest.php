<?php

use App\Domain\AdPlatforms\Exceptions\AdPlatformException;
use App\Domain\Leads\Enums\LeadSourceType;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Lodgely\LodgelyLeadSource;
use App\Domain\Leads\Services\LeadSync;
use App\Models\Client;
use App\Models\FetchLog;
use App\Models\Lead;
use App\Models\LeadSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;

/*
 * Contract tests for docs/LODGELY-CONTRACT.md v1, against fixtures. When
 * lodgely ships the API, record real responses into tests/Fixtures/lodgely
 * and these tests tell us whether both sides still agree.
 */
beforeEach(function () {
    Sleep::fake();
    config([
        'vigilo.mock_mode' => false,
        'vigilo.lodgely.enabled' => true,
        'vigilo.lodgely.url' => 'https://leads.agency.example',
        'vigilo.lodgely.token' => 'lodgely-read-only-token',
    ]);
    $this->source = LeadSource::query()->create([
        'client_id' => Client::factory()->create()->id,
        'type' => LeadSourceType::Lodgely,
        'external_key' => 'alpenblick-solar',
    ]);
});

it('follows the cursor, upserts status changes and applies deletions', function () {
    Http::fake(['leads.agency.example/api/v1/lead-metadata*' => Http::sequence()
        ->push(fixture('lodgely/page1.json'))
        ->push(fixture('lodgely/page2.json'))]);

    $result = app(LeadSync::class)->sync($this->source);

    expect($result['ok'])->toBeTrue()->and(Lead::query()->count())->toBe(1);
    $lead = Lead::query()->first();
    expect($lead->external_id)->toBe('ld_01J9Z6Q4K8')
        ->and($lead->status)->toBe(LeadStatus::Successful)
        ->and($lead->meta_ad_id)->toBe('2385011002')
        ->and($this->source->fresh()->cursor)->toBe(fixture('lodgely/page2.json')['next_cursor'])
        ->and(FetchLog::query()->where('kind', 'leads')->value('status'))->toBe('success');

    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer lodgely-read-only-token')
        && $r['client'] === 'alpenblick-solar' && isset($r['updated_since']) && ! isset($r['cursor']));
    Http::assertSent(fn (Request $r) => ($r['cursor'] ?? null) === fixture('lodgely/page1.json')['next_cursor']);
});

it('never stores personal data, even when lodgely sends it', function () {
    Http::fake(['leads.agency.example/*' => Http::response([...fixture('lodgely/page1.json'), 'has_more' => false])]);

    app(LeadSync::class)->sync($this->source);

    $dump = json_encode(Lead::query()->get()->map->getAttributes());
    expect(Lead::query()->count())->toBe(2)
        ->and($dump)->not->toContain('Should Never Be Stored')
        ->and($dump)->not->toContain('never@example.com')
        ->and($dump)->not->toContain('+49 30')
        ->and($dump)->not->toContain('free text');
    expect(Lead::query()->where('external_id', 'ld_01J9Z7B2M1')->value('has_gclid'))->toBeTruthy();
});

it('has no column that could hold lead PII', function () {
    $columns = Schema::getColumnListing('leads');

    expect($columns)->not->toContain('email')->not->toContain('name')->not->toContain('full_name')
        ->not->toContain('phone')->not->toContain('message')->not->toContain('answers')->not->toContain('gclid');
});

it('rejects a contract version it does not understand', function () {
    Http::fake(['leads.agency.example/*' => Http::response([...fixture('lodgely/page1.json'), 'contract_version' => 2])]);

    $result = app(LeadSync::class)->sync($this->source);

    expect($result['ok'])->toBeFalse()->and($result['error'])->toContain('contract version 2')
        ->and(Lead::query()->count())->toBe(0);
});

it('marks the source as auth-failed on 401', function () {
    Http::fake(['leads.agency.example/*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    app(LeadSync::class)->sync($this->source);

    expect($this->source->fresh()->status->value)->toBe('auth_failed')
        ->and($this->source->fresh()->consecutive_failures)->toBe(1);
});

it('stays off until the feature flag is enabled', function () {
    config(['vigilo.lodgely.enabled' => false]);
    Http::fake();

    $result = app(LeadSync::class)->sync($this->source);

    expect($result['ok'])->toBeFalse()->and($result['error'])->toContain('VIGILO_LODGELY_ENABLED');
    Http::assertNothingSent();
});

it('answers the connection test with the contract version', function () {
    Http::fake(['leads.agency.example/*' => Http::response(fixture('lodgely/page1.json'))]);

    expect(app(LeadSync::class)->test($this->source))->toBe(['ok' => true, 'message' => 'lodgely answered (contract v1).']);
    Http::assertSent(fn (Request $r) => $r['limit'] === 1);
});

it('requires a client key', function () {
    $this->source->update(['external_key' => null]);
    Http::fake();

    expect(fn () => app(LodgelyLeadSource::class)->fetch($this->source, null))
        ->toThrow(fn (AdPlatformException $e) => expect($e->errorCode)->toBe(AdPlatformException::CONFIG));
});

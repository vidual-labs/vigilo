<?php

use App\Domain\Checks\CheckResult;
use App\Domain\Findings\Data\FindingDraft;
use App\Domain\Findings\Enums\FindingEventType;
use App\Domain\Findings\Enums\FindingStatus;
use App\Domain\Findings\Enums\Severity;
use App\Domain\Findings\Services\FindingActions;
use App\Domain\Findings\Services\FindingReconciler;
use App\Models\Client;
use App\Models\Finding;
use Carbon\CarbonImmutable;

function draft(Client $client, Severity $severity = Severity::Warning, string $subject = 'conn:1'): FindingDraft
{
    return new FindingDraft('conversion_drop', "conversion_drop:{$client->id}:{$subject}", $severity, 'findings.conversion_drop.drop',
        ['account' => 'Acme', 'days' => 3, 'ratio' => ['type' => 'percent', 'value' => 0.3]], ['spend' => 100]);
}

function reconcile(Client $client, array $drafts, array $keep = [], ?CarbonImmutable $now = null): array
{
    $result = new CheckResult;
    foreach ($drafts as $d) {
        $result->add($d);
    }
    foreach ($keep as $k) {
        $result->keep($k);
    }

    return app(FindingReconciler::class)->reconcile($client, 'conversion_drop', $result, $now);
}

beforeEach(fn () => $this->client = Client::factory()->create());

it('opens a finding once and refreshes it on later runs without re-alerting', function () {
    $first = reconcile($this->client, [draft($this->client)]);
    $second = reconcile($this->client, [draft($this->client)]);

    expect($first['opened'])->toHaveCount(1)
        ->and($second['opened'])->toBeEmpty()->and($second['escalated'])->toBeEmpty()
        ->and(Finding::query()->count())->toBe(1)
        ->and(Finding::query()->first()->occurrences)->toBe(2);
});

it('renders the title in the reader\'s language', function () {
    reconcile($this->client, [draft($this->client)]);
    $finding = Finding::query()->first();

    expect($finding->title('en'))->toBe('Acme: conversions at 30% of normal over the last 3 days')
        ->and(spaces($finding->title('de')))->toBe('Acme: nur 30 % der üblichen Conversions in den letzten 3 Tagen');
});

it('escalates and re-opens an acknowledged finding when it gets worse', function () {
    reconcile($this->client, [draft($this->client)]);
    $finding = Finding::query()->first();
    app(FindingActions::class)->acknowledge($finding, $this->operator());

    $changes = reconcile($this->client, [draft($this->client, Severity::Critical)]);

    expect($changes['escalated'])->toHaveCount(1)
        ->and($finding->fresh()->severity)->toBe(Severity::Critical)
        ->and($finding->fresh()->status)->toBe(FindingStatus::Open);
});

it('lowers severity quietly when it gets better', function () {
    reconcile($this->client, [draft($this->client, Severity::Critical)]);
    $changes = reconcile($this->client, [draft($this->client, Severity::Warning)]);

    expect($changes['escalated'])->toBeEmpty()
        ->and(Finding::query()->first()->severity)->toBe(Severity::Warning)
        ->and(Finding::query()->first()->events()->where('type', FindingEventType::Deescalated->value)->exists())->toBeTrue();
});

it('auto-resolves when the condition clears and reopens when it returns', function () {
    reconcile($this->client, [draft($this->client)]);
    $resolved = reconcile($this->client, []);
    $finding = Finding::query()->first();

    expect($resolved['resolved'])->toHaveCount(1)
        ->and($finding->status)->toBe(FindingStatus::Resolved)
        ->and($finding->resolved_by)->toBeNull();

    $back = reconcile($this->client, [draft($this->client)]);
    expect($back['opened'])->toHaveCount(1)
        ->and($finding->fresh()->status)->toBe(FindingStatus::Open)
        ->and($finding->fresh()->reopen_count)->toBe(1)
        ->and(Finding::query()->count())->toBe(1);
});

it('does not resolve what a check could not evaluate', function () {
    reconcile($this->client, [draft($this->client)]);
    reconcile($this->client, [], keep: ["conversion_drop:{$this->client->id}:conn:1"]);

    expect(Finding::query()->first()->status)->toBe(FindingStatus::Open);
});

it('keeps a snooze until it ends, then reopens', function () {
    reconcile($this->client, [draft($this->client)]);
    $finding = Finding::query()->first();
    app(FindingActions::class)->snooze($finding, $this->operator(), 7);

    reconcile($this->client, [draft($this->client)], now: CarbonImmutable::now()->addDays(2));
    expect($finding->fresh()->status)->toBe(FindingStatus::Snoozed);

    reconcile($this->client, [draft($this->client)], now: CarbonImmutable::now()->addDays(8));
    expect($finding->fresh()->status)->toBe(FindingStatus::Open);
});

it('only touches findings of the same client and check', function () {
    $other = Client::factory()->create();
    reconcile($other, [draft($other)]);
    Finding::factory()->for($this->client)->create(['check_key' => 'landing_page']);

    reconcile($this->client, []);

    expect(Finding::query()->where('status', 'open')->count())->toBe(2);
});

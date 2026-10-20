<?php

use App\Domain\Notifications\WebhookDispatcher;
use App\Domain\Runs\Enums\RunStatus;
use App\Domain\Runs\Enums\RunTrigger;
use App\Domain\Runs\RunManager;
use App\Mail\CriticalFindingsMail;
use App\Mail\DailyDigestMail;
use App\Models\AdConnection;
use App\Models\Client;
use App\Models\Finding;
use App\Models\WebhookEndpoint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    Http::fake(['hooks.example/*' => Http::response(['ok' => true])]);
    $this->client = Client::factory()->create(['name' => 'Alpenblick Solar GmbH']);
    $this->client->budgets()->create(['platform' => 'google', 'monthly_amount' => 6000]);
    AdConnection::factory()->for($this->client)->scenario('tracking_broken')->create();
    $this->hook = WebhookEndpoint::query()->create(['name' => 'n8n', 'url' => 'https://hooks.example/vigilo', 'secret' => 'shh-secret']);
    $this->operator = $this->operator(['email' => 'ops@agency.example']);
    $this->viewer(['receives_digest' => false, 'notify_critical' => false]);
});

it('runs fetch and checks for every client, then sends the digest after a scheduled run', function () {
    $run = app(RunManager::class)->start(RunTrigger::Schedule);

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->stats['clients'])->toBe(1)
        ->and(Finding::query()->where('check_key', 'conversion_drop')->value('severity')->value)->toBe('critical');

    Mail::assertSent(DailyDigestMail::class, 1);
    Mail::assertSent(DailyDigestMail::class, fn (DailyDigestMail $m) => $m->hasTo('ops@agency.example') && $m->digest['counts']['critical'] === 1);
    // The digest carries the news in a scheduled run; no separate critical mail.
    Mail::assertNotSent(CriticalFindingsMail::class);
    Http::assertSent(fn (Request $r) => $r['event'] === 'finding.critical');
    Http::assertSent(fn (Request $r) => $r['event'] === 'digest' && $r['summary']['critical'] === 1);
});

it('mails new critical findings right away on a manual run, once', function () {
    app(RunManager::class)->start(RunTrigger::Manual, user: $this->operator);
    app(RunManager::class)->start(RunTrigger::Manual, user: $this->operator);

    Mail::assertSent(CriticalFindingsMail::class, 1);
    Mail::assertNotSent(DailyDigestMail::class);
    expect(Finding::query()->first()->notified_at)->not->toBeNull();
});

it('signs webhook deliveries', function () {
    app(RunManager::class)->start(RunTrigger::Manual);

    Http::assertSent(function (Request $r) {
        $expected = WebhookDispatcher::sign('shh-secret', $r->header('X-Vigilo-Timestamp')[0], $r->body());

        return $r->hasHeader('X-Vigilo-Signature', $expected)
            && $r->hasHeader('X-Vigilo-Event', 'finding.critical')
            && $r['finding']['client']['name'] === 'Alpenblick Solar GmbH'
            && str_starts_with($r['text'], '[critical] Alpenblick Solar GmbH');
    });
    expect($this->hook->fresh()->last_status)->toBe(200);
});

it('sends an all-clear digest when nothing is open', function () {
    AdConnection::query()->update(['settings' => ['mock_scenario' => 'healthy']]);
    $this->client->budgets()->delete();

    app(RunManager::class)->start(RunTrigger::Schedule);

    Mail::assertSent(DailyDigestMail::class, fn (DailyDigestMail $m) => array_sum($m->digest['counts']) === 0);
});

it('renders the digest in the recipient\'s language', function () {
    $this->operator->update(['locale' => 'de']);
    app(RunManager::class)->start(RunTrigger::Schedule);

    Mail::assertSent(DailyDigestMail::class, function (DailyDigestMail $mail) {
        $mail->locale('de');
        $html = $mail->render();

        return str_contains($html, 'Tägliche Zusammenfassung') && str_contains($html, 'keine Conversions');
    });
});

it('skips inactive clients and can run checks without fetching', function () {
    $this->client->update(['is_active' => false]);

    $run = app(RunManager::class)->start(RunTrigger::Cli, fetch: false);

    expect($run->status)->toBe(RunStatus::Completed)->and(Finding::query()->count())->toBe(0);
});

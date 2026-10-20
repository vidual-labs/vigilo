<?php

namespace App\Domain\Demo;

use App\Domain\AdPlatforms\Services\AdDataSync;
use App\Domain\Checks\LandingPages\LandingPageProbeService;
use App\Domain\Checks\Services\CheckRunner;
use App\Domain\Findings\Enums\FindingEventType;
use App\Domain\Findings\Enums\FindingStatus;
use App\Domain\Findings\Enums\Severity;
use App\Domain\Findings\Services\FindingActions;
use App\Domain\Findings\Services\FindingReconciler;
use App\Domain\Leads\Enums\LeadSourceType;
use App\Domain\Leads\Services\LeadSync;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Finding;
use App\Models\User;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Realistic DACH B2B demo clients on simulated (mock) connections. Each
 * scenario is chosen so a fresh install shows every check firing at least
 * once — plus one client where everything is fine.
 *
 * The data then flows through the real pipeline (sync → probes → checks →
 * reconciler); the seeder only adds a little history on top
 * (an acknowledged finding, a resolved one).
 */
class DemoSeeder
{
    public const FLAG = 'demo.seeded_at';

    /**
     * name, timezone, currency, connections [platform => [scenario, budget]], lead source [key, scenario]|null, monitored urls
     *
     * @var list<array{0: string, 1: string, 2: string, 3: array<string, array{0: string, 1: int}>, 4: array{0: string, 1: string}|null, 5: array<string, string>}>
     */
    private const CLIENTS = [
        ['Alpenblick Solar GmbH', 'Europe/Berlin', 'EUR', ['google' => ['tracking_broken', 6000], 'meta' => ['healthy', 3500]], ['alpenblick-solar', 'healthy'], ['https://www.alpenblick-solar.example/rechner' => 'Solarrechner']],
        ['Kanzlei Brenner & Partner', 'Europe/Vienna', 'EUR', ['google' => ['overspend', 4000], 'meta' => ['auth_failed', 1500]], ['kanzlei-brenner', 'healthy'], ['https://termine.kanzlei-brenner.example/' => 'Online-Terminbuchung']],
        ['Nordwerk Maschinenbau AG', 'Europe/Berlin', 'EUR', ['google' => ['disapproved_ads', 8000], 'meta' => ['token_expiring', 2500]], null, ['https://www.nordwerk-maschinenbau.example/webinar' => 'Webinar-Anmeldung']],
        ['MediCare Praxisnetz Zürich', 'Europe/Zurich', 'CHF', ['meta' => ['cpl_spike', 5000], 'google' => ['broken_landing_page', 3000]], ['medicare-zuerich', 'healthy'], []],
        ['Huber Steuerberatung', 'Europe/Berlin', 'EUR', ['google' => ['healthy', 2500], 'meta' => ['underspend', 2000]], ['huber-steuerberatung', 'sync_broken'], ['https://alt.huber-steuerberatung.example/kontakt' => 'Altes Kontaktformular']],
        ['Lichtblick Akustik GmbH', 'Europe/Berlin', 'EUR', ['google' => ['conversion_drop', 5000], 'meta' => ['limited_ads', 2000]], ['lichtblick-akustik', 'healthy'], []],
        ['Seeberger Holzbau', 'Europe/Berlin', 'EUR', ['google' => ['healthy', 3000], 'meta' => ['healthy', 1500]], ['seeberger-holzbau', 'healthy'], ['https://www.seeberger-holzbau.example/' => 'Startseite']],
    ];

    public function __construct(
        private readonly AdDataSync $ads,
        private readonly LeadSync $leads,
        private readonly LandingPageProbeService $probes,
        private readonly CheckRunner $checks,
        private readonly FindingActions $actions,
        private readonly FindingReconciler $reconciler,
    ) {}

    public static function alreadySeeded(): bool
    {
        return AppSetting::get(self::FLAG) !== null;
    }

    /** @return int clients created */
    public function seed(): int
    {
        $count = 0;
        foreach (self::CLIENTS as $i => [$name, $tz, $currency, $connections, $leadSource, $monitored]) {
            $client = Client::query()->create([
                'name' => $name,
                'slug' => Str::slug($name),
                'timezone' => $tz,
                'currency' => $currency,
                'is_demo' => true,
                'notes' => __('Demo client with simulated data.'),
            ]);

            $n = 0;
            foreach ($connections as $platform => [$scenario, $budget]) {
                $n++;
                $client->budgets()->create(['platform' => $platform, 'monthly_amount' => $budget]);
                $client->connections()->create([
                    'platform' => $platform,
                    'account_id' => $platform === Platform::Google->value
                        ? sprintf('%03d%03d%04d', 410 + $i, 200 + $n, 1000 + $i * 37)
                        : 'act_'.(1204000000 + $i * 1013 + $n),
                    'login_customer_id' => $platform === Platform::Google->value ? '5550001234' : null,
                    'settings' => ['mock_scenario' => $scenario],
                ]);
            }

            if ($leadSource !== null) {
                $client->leadSource()->create([
                    'type' => LeadSourceType::Lodgely,
                    'external_key' => $leadSource[0],
                    'settings' => ['mock_scenario' => $leadSource[1]],
                ]);
            }

            foreach ($monitored as $url => $label) {
                $client->monitoredUrls()->create(['url' => $url, 'label' => $label]);
            }

            $this->runPipeline($client->fresh());
            $count++;
        }

        $this->addHistory();
        AppSetting::put(self::FLAG, now()->toIso8601String());

        return $count;
    }

    public function remove(): int
    {
        $count = Client::query()->where('is_demo', true)->count();
        Client::query()->where('is_demo', true)->get()->each->delete();
        // Keep the flag so the next container start does not seed them again.
        AppSetting::put(self::FLAG, 'removed '.now()->toIso8601String());

        return $count;
    }

    private function runPipeline(Client $client): void
    {
        foreach ($client->connections as $connection) {
            $this->ads->sync($connection);
        }
        if ($client->leadSource) {
            $this->leads->sync($client->leadSource);
        }
        // Twice, like two daily runs: a landing page only alerts after two
        // failures in a row.
        $this->probes->probeClient($client);
        $this->probes->probeClient($client);

        $this->checks->run($client);
    }

    /** A bit of believable history: earlier detection, an acknowledgement, a resolved finding. */
    private function addHistory(): void
    {
        $operator = User::query()->where('role', UserRole::Operator->value)->orderBy('id')->first();

        // Spread first detection over the last days so the inbox does not look brand new.
        foreach (Finding::query()->whereHas('client', fn ($q) => $q->where('is_demo', true))->get() as $finding) {
            $daysAgo = $finding->severity === Severity::Critical ? $finding->id % 2 : 1 + $finding->id % 4;
            $at = CarbonImmutable::now()->subDays($daysAgo)->setTime(6, 4 + $finding->id % 20);
            $finding->forceFill(['first_detected_at' => $at, 'opened_at' => $at, 'occurrences' => $daysAgo + 1])->save();
            $finding->events()->update(['created_at' => $at]);
        }

        if ($operator !== null) {
            $token = Finding::query()->where('check_key', 'connection_health')->where('dedupe_key', 'like', '%:token')->first();
            if ($token !== null) {
                $this->actions->acknowledge($token, $operator);
            }
        }

        // A budget alert from earlier this month that has since cleared.
        $seeberger = Client::query()->where('slug', 'seeberger-holzbau')->first();
        if ($seeberger !== null) {
            $opened = CarbonImmutable::now()->subDays(9)->setTime(6, 7);
            $resolved = CarbonImmutable::now()->subDays(5)->setTime(6, 9);
            $finding = Finding::query()->create([
                'client_id' => $seeberger->id,
                'check_key' => 'budget_pacing',
                'dedupe_key' => 'budget_pacing:'.$seeberger->id.':google',
                'platform' => Platform::Google,
                'severity' => Severity::Warning,
                'status' => FindingStatus::Resolved,
                'title_key' => 'findings.budget_pacing.overpacing',
                'title_params' => [
                    'platform' => ['type' => 'platform', 'value' => 'google'],
                    'spend' => Format::m(1410, 'EUR'),
                    'budget' => Format::m(3000, 'EUR'),
                    'pace' => Format::p(1.21),
                    'projected' => Format::m(3640, 'EUR'),
                ],
                'evidence' => ['monthly_budget' => Format::m(3000, 'EUR'), 'pace' => Format::p(1.21)],
                'first_detected_at' => $opened,
                'last_detected_at' => $resolved->subDay(),
                'opened_at' => $opened,
                'resolved_at' => $resolved,
                'occurrences' => 4,
            ]);
            $this->reconciler->event($finding, FindingEventType::Opened, $opened, ['severity' => 'warning']);
            $this->reconciler->event($finding, FindingEventType::Resolved, $resolved, ['auto' => true]);
        }
    }
}

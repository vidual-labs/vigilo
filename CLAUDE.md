# Working notes for Claude / future maintainers

A concise orientation (the README is the full reference). It exists to keep
contributors — human or AI — on the architectural rails, and to record the
gotchas that already cost time once.

## Product north star

vigilo watches every client's ad accounts once a day and tells the agency
**what is broken** (watchdog) and **what is wasting money** (miner, Phase 2),
in one findings inbox and one daily digest.

**Non-goals** — push back on requests that drift here:

- **Not a reporting dashboard.** lodgely does reporting. No charts of spend
  over time, no client-facing reports.
- **Not a CRM.** Leads live in lodgely. vigilo reads lead *metadata* only.
- **Not a bid manager or automation engine.** v1 is **strictly read-only**
  towards ad platforms: it suggests, it never changes campaigns, budgets or
  keywords, and it requests read-only access only.

Personas: **operator** (agency person, sees and changes everything) and
**viewer** (sees everything, changes nothing). Client portal logins are a
later phase.

## Tech stack

PHP 8.4 · Laravel 12 · Livewire 3 + Alpine (bundled with Livewire) ·
Blade-first · Tailwind CSS 4 · PostgreSQL 16 (only supported DB; tests use
in-memory SQLite) · database queue (no Redis) · Caddy 2 · Pest · Pint ·
Larastan (level 5). No dependencies beyond Laravel's ecosystem; Google Ads
and Meta are called with Laravel's HTTP client (no vendor SDKs).

## Architecture map

| Concern | Where |
|---|---|
| Ad platform contract + DTOs | `app/Domain/AdPlatforms/Contracts`, `Data` |
| Google Ads (REST + GAQL) / Meta (Graph) adapters | `app/Domain/AdPlatforms/Google`, `Meta` |
| Mock adapters + scenarios | `app/Domain/AdPlatforms/Mock` |
| Adapter registry (mock vs live) | `AdPlatformRegistry::ADAPTERS` |
| HTTP retries / backoff / rate limits | `AdPlatforms/Http/ResilientHttp` |
| Persisting fetched data + fetch logs | `AdPlatforms/Services/AdDataSync`, `FetchLogger` |
| Lead source contract, allow-list DTO | `app/Domain/Leads/Contracts/LeadSource`, `Data/LeadMetadata` |
| lodgely client (contract v1) / mock | `Leads/Lodgely`, `Leads/Mock`, spec in `docs/LODGELY-CONTRACT.md` |
| Checks + registry | `app/Domain/Checks/Checks/*`, `CheckRegistry::CHECKS` |
| What checks read | `Checks/Snapshot/ClientSnapshot` (built by `SnapshotFactory`) |
| Thresholds (client → install → config) | `Checks/Services/ThresholdResolver`, defaults in `config/vigilo.php` |
| Landing-page probing (GET only) | `Checks/LandingPages/*` |
| Finding lifecycle (dedupe, escalate, resolve, reopen) | `Findings/Services/FindingReconciler` |
| User actions on findings | `Findings/Services/FindingActions` |
| Digest, critical mails, webhooks | `app/Domain/Notifications`, `app/Mail`, `Jobs/DeliverWebhook` |
| Daily run orchestration | `Runs/RunManager` + `Jobs/SyncClientData`, `Jobs/RunClientChecks` |
| Schedule | `routes/console.php` |
| UI | `app/Livewire/*`, `resources/views/livewire/*` |
| Translations | `lang/de.json` (UI strings, English source), `lang/{en,de}/{checks,findings,evidence,mock}.php` |
| Visibility seam | `Client::scopeVisibleTo()`, `Finding::scopeVisibleTo()` |
| Docker | `Dockerfile`, `docker-compose.yml`, `docker/` |

**Data flow of a run:** `RunManager::start()` → one batch, per client a chain
`SyncClientData` (ads via `AdDataSync`, leads via `LeadSync`, landing pages
via `LandingPageProbeService`) → `RunClientChecks` (`CheckRunner` builds a
`ClientSnapshot`, runs every check, `FindingReconciler` applies the result,
`Notifier::critical()` alerts) → batch `finally` → `RunManager::finish()` →
`SendDigests` for the scheduled run.

## How to add a check

1. **Class.** `app/Domain/Checks/Checks/FooCheck.php` extending `BaseCheck`:
   `key()` (stable, snake_case), `fields()` (threshold name → `int|float|ratio|severity|int_list`),
   `run(ClientSnapshot $snapshot, array $thresholds): CheckResult`.
   - Checks are **pure**: read only the snapshot. If you need new data, add
     it to `ClientSnapshot` + `SnapshotFactory` (and to the sync, if it has to
     be fetched).
   - Build findings with `$this->draft($snapshot, $subject, Severity, 'title', params, evidence, platform)`.
     `$subject` makes the dedupe key `<check>:<client>:<subject>` — the same
     problem must always produce the same subject.
   - Params and evidence values that are money/percent/factor/translations
     use the typed helpers in `App\Support\Format` (`m()`, `p()`, `x()`,
     `n()`, `t()`), so they render in the reader's language.
   - If you cannot evaluate a subject (stale data: `! $snapshot->isFresh(...)`),
     call `$result->keep($dedupeKeyPrefix)` so open findings are not
     auto-resolved.
2. **Register** it in `CheckRegistry::CHECKS`.
3. **Defaults** in `config/vigilo.php` under `checks.<key>` (with `enabled`).
4. **Strings** in `lang/en/checks.php` and `lang/de/checks.php` (`name`,
   `description`, `advice`, `fields.*`) and the titles in
   `lang/{en,de}/findings.php`. Plural titles use `{1} …|[2,*] …` with a
   `count`, `days` or `hours` param.
5. **Tests** in `tests/Unit/Checks/FooCheckTest.php` using
   `Tests\Support\SnapshotBuilder` (fluent or a JSON fixture in
   `tests/Fixtures/snapshots/`): at least one firing case per severity, one
   quiet case, and the stale-data case. `TranslationsTest` will fail if a
   title or label is missing in either language.
6. **Demo** (optional but nice): a mock scenario in `MockScenarios` and a
   demo client in `DemoSeeder` that triggers it.
7. **Docs:** README *Features* table, CHANGELOG under *Unreleased*.

## Conventions

- Domain code in `app/Domain/<Area>`; Eloquent models in `app/Models`;
  enums, never raw strings, in the domain layer.
- Adapters are fetch-and-map classes returning DTOs; persistence is the
  sync service's job.
- Every user-facing query goes through `visibleTo($user)`; every mutating
  Livewire action calls `$this->authorizeOperator()` (viewers get 403
  server-side, not just hidden buttons).
- Finding titles/evidence are stored as translation keys + typed params,
  never as rendered text.
- English source strings in `__('…')`; the German translation goes into
  `lang/de.json` in the same change. German uses the formal "Sie", like
  lodgely.
- Money columns are `decimal`; dates for "yesterday" / month-to-date are
  computed in the client's timezone (`Client::now()`, `ClientSnapshot::today()`).
- Small, focused commits with a clear message.

## Hard rules

1. **v1 is read-only towards ad platforms.** No mutate calls, no write
   scopes, GET-only for Meta, `googleAds:search` only for Google. Tests
   assert this; do not weaken them.
2. **Never store lead PII.** No column for names, emails, phone numbers,
   free-text answers, IPs or click ids. New lead fields go through the
   `LeadMetadata` allow-list and must be metadata.
3. **Landing-page checks never submit forms** — GET requests only.
4. **Every user-facing change gets a CHANGELOG entry under "Unreleased".**
5. **Keep README.md and CLAUDE.md in sync with the code** (features, env
   vars, commands, architecture).
6. **EN + DE strings for all UI text** (`TranslationsTest` enforces it).
7. **Tests for every check** (unit tests on fixture data) and for every
   adapter (HTTP fakes + fixtures).
8. **Tests never touch a real database** — see the gotcha below.

## Commands

```bash
# Docker (production-like)
cp .env.example .env && docker compose up -d
docker compose logs app | grep -A2 "first operator"     # generated password
docker compose exec app php artisan test                # tests (in-memory SQLite)
docker compose exec app php artisan vigilo:run --sync   # fetch + checks now
docker compose exec app php artisan vigilo:digest       # send digests now
docker compose exec app php artisan vigilo:demo:remove  # drop demo clients
docker compose up -d --build                            # after code changes

# Local
php artisan test
vendor/bin/pint                 # format (CI runs --test)
vendor/bin/phpstan analyse      # Larastan, level 5
npm run build                   # assets
```

## Gotchas (learned the hard way)

- **`php artisan test` inside a container used to wipe the live database.**
  Docker passes `.env` as real environment variables, which PHP puts into
  `$_SERVER`; Laravel reads `$_SERVER` before `$_ENV`, so phpunit's
  `<env force="true">` alone lost and `RefreshDatabase` ran against
  Postgres. `phpunit.xml` therefore sets every value as `<server>` **and**
  `<env>`, and `Tests\TestCase::createApplication()` refuses to boot against
  anything but in-memory SQLite. Keep both.
- **Alpine's ICU is English-only.** Without `icu-data-full`, German money
  and percentages silently format the English way. The Dockerfile installs
  it; keep it.
- **Larastan needs `parseModelCastsMethod: true`** (phpstan.neon) to see
  `casts()` types; otherwise every cast attribute looks like a string.
- **Tailwind 4 cannot `@apply` a custom class.** Shared building blocks
  (`v-btn`, `v-input`, …) are `@utility` definitions in `resources/css/app.css`.
- **Enum-keyed `pluck()` breaks.** `pluck('n', 'severity')` on a model with
  an enum cast returns enum keys; use `->toBase()->pluck(...)` for counts.
- **Missing data is not "fixed".** A check that cannot evaluate must
  `keep()` the dedupe prefix; otherwise a failed fetch auto-resolves real
  problems.
- **The scheduled run suppresses immediate critical mails** (the digest
  follows minutes later); manual runs and *Fetch now* mail right away.
  Webhooks always fire.
- **Queue `retry_after` (1000 s) must stay above the longest job timeout**
  (`SyncClientData`, 900 s), or a slow fetch runs twice.
- **The demo flag survives removal** (`AppSetting demo.seeded_at`), so
  `vigilo:demo:remove` is not undone by the next container start.

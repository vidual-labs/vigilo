# Changelog

All notable changes to vigilo are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and vigilo uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html). Every
user-facing change gets an entry under **Unreleased**.

## [Unreleased]

### Added

- **Meta conversion action types per connection.** A Meta connection can
  override `VIGILO_META_CONVERSION_ACTION_TYPES` (*Connections* → edit →
  *Conversion action types*), for accounts that optimise for a custom
  conversion such as `offsite_conversion.custom.<id>`.
- **Google keyword attribution fields.** Leads accept and store the optional
  contract fields `google_campaign_id`, `google_ad_group_id` and
  `google_criterion_id` (numeric ValueTrack ids only; anything else is
  dropped). lodgely sends them once it stores them per lead (LG-6); they are
  the basis for keyword-level lead quality (VG-9). Needs `php artisan
  migrate` (runs on container start).

### Changed

- **Conversion drop skips the most recent day for Google** by default
  (`google_skip_recent_days`, default 1, per install and per client), because
  Google keeps attributing conversions to the click date for days and an
  incomplete "yesterday" raised false alarms. Meta is unchanged.
- **The lodgely integration is on once `VIGILO_LODGELY_URL` and
  `VIGILO_LODGELY_API_TOKEN` are set.** `VIGILO_LODGELY_ENABLED` is gone; an
  existing `VIGILO_LODGELY_ENABLED=false` with a URL and token set no longer
  keeps the integration off.
- **Leads flagged as duplicates by lodgely no longer count** towards lead
  totals and cost per lead, even when their status was changed. vigilo and
  lodgely's reporting now count leads the same way.
- OPEN-ITEMS and the *Shared with vigilo* ROADMAP section record the
  decisions on Q-4 – Q-6 and Q-9 – Q-13.
- **lodgely ships the lead-metadata API** (lodgely 0.59.0, LG-15).
  `docs/LODGELY-CONTRACT.md` is marked implemented and lists what lodgely
  sends today (`ld_` ids, slug client keys, `has_gclid` always `false` until
  lodgely LG-6, no `spam` flag). The ROADMAP gains a *Shared with vigilo*
  section mirrored in lodgely's ROADMAP, and OPEN-ITEMS records the decisions
  on Q-1 – Q-3.

### Fixed

- **A deactivated user is locked out of pages that are already open.** Until
  now only the next page load signed them out; filters and other Livewire
  requests on an open page kept returning data. The same applies to a user
  whose password was reset: they are sent to the profile page right away.
- **The plain-text part of the digest and critical mails** no longer shows
  HTML entities (`Brenner &amp; Partner`).
- **Changing a client's lodgely key no longer raises a false *spend without
  leads* alarm.** The new feed started with the old feed's health, so its
  empty lead list looked fresh until the first successful fetch. A changed
  key or source now starts over completely (no cursor, no leads, no health
  state, no findings of the old feed).
- **Pointing a connection at another ad account or client starts it over.**
  The old account's metrics, ads and last success stayed, so until the next
  fetch the checks judged the old numbers as current (moved to another
  client, a connection showed 0 % spend, "spending behind plan").
- **Conversion drop no longer says "yesterday" for Google.** Google's
  windows end a day earlier (`google_skip_recent_days`), so the title now
  names the day it judged ("no conversions on 3 Nov 2026", "in the 3 days up
  to 3 Nov 2026"). Meta findings keep "yesterday" / "the last N days".
- **A landing page that fails to load once no longer resolves its other
  findings.** A slow page that timed out, or a page whose probe did not run
  (older than 48 hours), auto-resolved its *slow*, *redirects*, *off-site*,
  *insecure* and certificate findings, which then reopened and alerted again
  the next day. They now stay as they are until the page can be judged.
- ***Spend without leads* is no longer resolved by a failed fetch.** When one
  ad account could not be fetched and the others alone spent too little, the
  open finding was resolved although the missing account may be the one
  spending. It now stays open until vigilo can see all accounts again or a
  lead arrives.
- **Adding a user, or changing your email, with an address that already
  exists in different capitals** (`Jana@…` for `jana@…`) shows "already
  taken" instead of a server error.
- **An invalid check threshold no longer saves half the form.** Client
  settings and the *Checks* page stored everything before the bad field
  (name, budgets, lead source, earlier checks) and then showed the error.
  Now nothing is saved until every field is valid.
- **Run statistics** (*Checks* → *Runs*, `vigilo:run`) of a single-client run
  count only that client's new and resolved findings and fetch errors, and a
  full run counts the clients it actually ran (demo clients are skipped
  outside mock mode).
- **Undoing an acknowledgement shows as "Reopened"** in a finding's history,
  not as "Snooze ended".
- **Check settings in German show inherited decimals with a comma** (`0,15`
  instead of `0.15`), and the on/off selector of every check sits next to
  its description instead of sometimes wrapping below it.

## [0.1.0] - 2026-10-20

First release: the watchdog (Phase 1).

### Added

- **Seven watchdog checks**, each with install-wide and per-client thresholds:
  conversion drop (Google + Meta), spend without leads, connection health
  (auth failures, stale data, Meta token expiry, disabled ad accounts),
  budget pacing, disapproved and limited ads, CPL spike, and landing-page
  health (status, redirect chains, off-site redirects, HTTPS downgrades,
  response time, TLS expiry — GET only, forms are never submitted).
- **Findings inbox** with filters (client, severity, status, check,
  platform), search, acknowledge, snooze (1/7/30 days), resolve, bulk
  actions, a detail page with evidence and history. Findings are deduped by
  a stable key, escalate when they get worse, resolve themselves when the
  condition clears and reopen when it returns; failed fetches never resolve
  a finding.
- **Read-only Google Ads adapter** (REST + GAQL, API v26, OAuth refresh
  token, developer token, manager login-customer-id, Performance Max asset
  groups) and **Meta Marketing API adapter** (Graph v26.0, system-user
  token, `appsecret_proof`, token expiry via `debug_token`), with retries,
  exponential backoff, rate-limit handling and a log of every fetch.
- **Lead source contract** (`docs/LODGELY-CONTRACT.md`) with a lodgely
  client behind `VIGILO_LODGELY_ENABLED` and contract tests. Only lead
  metadata is stored — never names, emails, phone numbers, answers or click
  ids.
- **Daily run** (fetch → checks → digest) on the scheduler, manual runs per
  install or client, *Test* and *Fetch now* per connection.
- **Notifications:** daily digest email per user (critical first, in the
  reader's language), immediate mails for new critical findings outside the
  daily run, HMAC-SHA256-signed webhooks (`finding.critical`, `digest`,
  `ping`) with a Slack-compatible `text` field.
- **UI:** clients (budgets, lead source, monitored URLs, check overrides),
  connections, check settings with run history, notifications, users
  (operator/viewer), profile; English and German; dark/light mode;
  per-user language.
- **Mock mode with demo data**, on by default: seven demo clients on
  simulated connections that trigger every check.
- **Docker Compose install** (app, queue, scheduler, Postgres 16, Caddy 2):
  `cp .env.example .env && docker compose up -d` gives a working install —
  generated `APP_KEY`, migrations, first operator with a generated password
  (weak or default passwords are refused), demo data, healthchecks on every
  service, tuned for a 512 MB VPS.
- Retention for fetch logs, metrics, lead metadata, resolved findings and
  runs (`vigilo:prune`, daily).
- CI: Pint, Larastan, Pest and a full `docker compose` install with the test
  suite run inside the container.

### Security

- Viewers are read-only server-side; per-connection tokens and webhook
  secrets are encrypted at rest; login and password-reset are rate limited;
  reset links are always built from `APP_URL`; operators with a generated
  password must change it on first sign-in.
- The test suite refuses to run against anything but in-memory SQLite, so
  `php artisan test` inside a production container cannot touch live data.

[Unreleased]: https://github.com/vidual-labs/vigilo/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/vidual-labs/vigilo/releases/tag/v0.1.0

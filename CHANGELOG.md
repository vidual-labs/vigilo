# Changelog

All notable changes to vigilo are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and vigilo uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html). Every
user-facing change gets an entry under **Unreleased**.

## [Unreleased]

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

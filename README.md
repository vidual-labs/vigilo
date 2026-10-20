# vigilo

**A self-hosted ads watchdog for agencies.** Once a day vigilo looks at
every client's Google Ads and Meta Ads accounts and tells you what is broken
and what is wasting money — in one findings inbox and one daily digest.

*vigilo* is Latin for "I keep watch".

![version](https://img.shields.io/badge/version-0.1.0-0f766e) ![license](https://img.shields.io/badge/license-AGPL--3.0--or--later-blue) ![php](https://img.shields.io/badge/PHP-8.4-777bb4)

Sibling of [lodgely](https://github.com/vidual-labs/lodgely) (lead intake),
[OpenFlow](https://github.com/vidual-labs/openflow) (forms) and calon (booking).

---

## Table of contents

- [What vigilo is — and is not](#what-vigilo-is--and-is-not)
- [Who it is for](#who-it-is-for)
- [Features](#features)
- [Quick start (Docker)](#quick-start-docker)
- [Configuration reference](#configuration-reference)
- [Connecting Google Ads](#connecting-google-ads)
- [Connecting Meta Ads](#connecting-meta-ads)
- [lodgely integration](#lodgely-integration)
- [Webhooks](#webhooks)
- [Commands](#commands)
- [Running in production](#running-in-production)
- [Privacy & GDPR notes for self-hosters](#privacy--gdpr-notes-for-self-hosters)
- [Architecture at a glance](#architecture-at-a-glance)
- [Development](#development)
- [Roadmap](#roadmap)
- [License](#license)

---

## What vigilo is — and is not

vigilo **is** a watchdog. It fetches data once a day, runs a fixed set of
checks, and raises *findings*: "conversions stopped yesterday", "no lead for
48 hours although €320 was spent", "this landing page returns 404". Each
problem is one finding — not one alert per day — and it resolves itself when
the problem goes away.

vigilo **is not**

- **a reporting dashboard** — lodgely does reporting;
- **a CRM** — leads live in lodgely; vigilo only reads their metadata;
- **a bid manager or automation engine** — vigilo v1 is strictly
  **read-only** towards ad platforms. It suggests; it never changes a
  campaign, budget or keyword, and it only asks for read access.

## Who it is for

Agencies that run Google Ads and Meta Ads for B2B lead-generation clients,
typically in the DACH region and GDPR-sensitive, who want to notice broken
tracking, dead forms, runaway budgets and disapproved ads **before** the
client does. One install serves the whole agency.

## Features

**Seven watchdog checks** (all thresholds adjustable per install and per client):

| Check | What it catches | Default |
|---|---|---|
| **Conversion drop** | Conversions over the last 1 or 3 complete days are zero, or below 50 % of the trailing 14-day baseline, while the account keeps spending. Google + Meta. | critical at zero, warning below 50 %; needs ≥ €50 spend in the window and ≥ 5 baseline conversions |
| **Spend without leads** | Ads keep spending but the lead source has received no new lead for N hours — a broken form, webhook or sync. | 48 h, ≥ €30 spend, critical |
| **Connection health** | Failed authentication, no successful fetch for too long, Meta token expiring within 14 days, disabled/unsettled ad account. Covers the lead source too. | warning after 26 h, critical after 72 h; token warning 14 d, critical 3 d |
| **Budget pacing** | Month-to-date spend vs. the pro-rata monthly budget per platform. | warning outside ±15 %, critical when already over budget or projected > 125 %; from day 3 |
| **Disapproved / limited ads** | Google policy approval status (`DISAPPROVED`, `APPROVED_LIMITED`, …) and Meta `effective_status` (`DISAPPROVED`, `WITH_ISSUES`) on ads in running campaigns. One finding per account, ads listed. | warning; critical when a campaign has no approved ad left; limited = info |
| **CPL spike** | Cost per lead over the last 7 days vs. the 28 days before, per platform. Uses attributed leads from the lead source when available, platform conversions otherwise. | warning at 1.5×, critical at 2× |
| **Landing-page health** | Final URLs of active ads plus monitored URLs: HTTP status, redirect chains, off-site redirects, HTTPS downgrades, response time, TLS expiry. **Plain GET only — vigilo never submits a form.** | critical after 2 failures in a row; warnings for > 2 redirects, > 3 s, TLS < 14 d (critical < 3 d) |

**Findings inbox** — filter by client, severity, status, check and platform;
acknowledge, snooze (1/7/30 days), resolve, bulk actions, full history per
finding. Lifecycle:

- the same problem keeps one stable *dedupe key*, so it is one finding, not a daily re-alert;
- it **escalates** (and alerts again) when it gets worse;
- it **auto-resolves** when the condition clears, and **reopens** if it comes back;
- missing data never resolves anything: when a fetch fails, the finding stays.

**Notifications** — a daily digest email per user (critical first, "new"
markers, in the reader's language), immediate mails for new critical
findings found outside the daily run, and outgoing **HMAC-signed webhooks**
(n8n, Make, Slack-compatible `text` field).

**Everything else** — clients with budgets, monitored URLs and per-client
check overrides; connections page with *Test* and *Fetch now* and a fetch
log; check settings; users (operator / viewer); profile; EN + DE everywhere;
dark/light mode; per-user language; **mock mode with demo data on by
default**, so a fresh install shows realistic clients and findings at once.

## Quick start (Docker)

You need Docker with Compose v2. On a fresh clone:

```bash
cp .env.example .env
docker compose up -d
```

That is all. The first start builds the images, creates the database,
generates an application key, creates the first operator and loads seven
demo clients on simulated connections. When `docker compose ps` shows all
five services *healthy*, open **http://localhost:8080**.

The first operator's password is printed once in the app log:

```bash
docker compose logs app | grep -A2 "first operator"
```

You will be asked to choose your own password after signing in. (Set
`ADMIN_EMAIL` / `ADMIN_PASSWORD` in `.env` before the first start to pick
them yourself — vigilo refuses to start with a weak or default password.)

Services: `postgres` (PostgreSQL 16), `app` (PHP-FPM), `queue` (database
queue worker), `scheduler` (daily run, digests, retention) and `caddy` (web
server, automatic HTTPS when you set a domain). Data lives in the named
volumes `pgdata` and `storage`.

Run the test suite inside the running install (uses in-memory SQLite and
refuses to touch the live database):

```bash
docker compose exec app php artisan test
```

**Going live:** add your Google/Meta credentials to `.env` (see below), set
`VIGILO_MOCK_MODE=false`, remove the demo clients with
`docker compose exec app php artisan vigilo:demo:remove`, and run
`docker compose up -d` again so the containers pick up the new environment.

## Configuration reference

All settings come from `.env` (fully commented in
[`.env.example`](.env.example)). Changes take effect after
`docker compose up -d`.

### Application

| Variable | Default | Meaning |
|---|---|---|
| `APP_NAME` | `vigilo` | Name in the browser title and mails. |
| `APP_ENV` | `local` | `production` once served over HTTPS. |
| `APP_KEY` | *(empty)* | Encryption key. Empty = generated on first start and kept in the `storage` volume (`storage/app/.app-key`). **Back it up**: without it, stored tokens and webhook secrets cannot be decrypted. |
| `APP_DEBUG` | `false` | Never `true` on a live install. |
| `APP_URL` | `http://localhost:8080` | Public address; links in mails/webhooks use it. An `https://` URL makes vigilo generate https links. |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `en` | Default language (`en`, `de`) for guests and new users. |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL` | `stack`, `single`, `warning` | Laravel logging; the log lives in `storage/logs/laravel.log`. |
| `TRUSTED_PROXIES` | private ranges | Proxies whose `X-Forwarded-*` headers are trusted. |

### First operator

| Variable | Default | Meaning |
|---|---|---|
| `ADMIN_NAME` | `Admin` | Name of the first operator. |
| `ADMIN_EMAIL` | `admin@example.com` | Email of the first operator (created only when no operator exists). |
| `ADMIN_PASSWORD` | *(empty)* | Empty = generated and printed once. Otherwise ≥ 12 characters, not a well-known/default password, not containing the email — or the app refuses to start. |

### Docker and web server

| Variable | Default | Meaning |
|---|---|---|
| `VIGILO_HTTP_PORT` / `VIGILO_HTTPS_PORT` | `8080` / `8443` | Host ports mapped to Caddy. |
| `VIGILO_SITE_ADDRESS` | `:80` | Caddy site address. A domain (e.g. `vigilo.agency.example`) enables automatic Let's Encrypt; map ports 80/443 for that. |
| `VIGILO_IMAGE_TAG` | `local` | Tag of the locally built images. |
| `PHP_FPM_MAX_CHILDREN` | `4` | PHP-FPM workers (each ~30–40 MB while busy). |

### Database, sessions, cache, queue

| Variable | Default | Meaning |
|---|---|---|
| `DB_CONNECTION` | `pgsql` | PostgreSQL is the only supported database. |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `5432` | Compose overrides the host to `postgres`. |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `vigilo` / `vigilo` / `vigilo-local-db` | Postgres is only reachable inside the compose network; change the password before the first start if you like. |
| `SESSION_DRIVER` / `SESSION_LIFETIME` | `database` / `480` | Sessions (minutes). |
| `SESSION_SECURE_COOKIE` | `false` | `true` once served over HTTPS. |
| `CACHE_STORE` | `database` | No Redis needed. |
| `QUEUE_CONNECTION` | `database` | The `queue` container works the queue. |

### Mail

| Variable | Default | Meaning |
|---|---|---|
| `MAIL_MAILER` | `log` | `log` writes mails to `storage/logs/mail.log`; use `smtp` for real delivery. |
| `MAIL_LOG_CHANNEL` | `mail` | Log channel for `MAIL_MAILER=log`. |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` | — | SMTP settings. |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | `vigilo@example.com` / `vigilo` | Sender. |

### vigilo

| Variable | Default | Meaning |
|---|---|---|
| `VIGILO_MOCK_MODE` | `true` | Simulate every integration (ad platforms, lead source, landing pages). Turn off to go live. |
| `VIGILO_DEMO_DATA` | `true` | Load demo clients on first start (mock mode only, once). |
| `VIGILO_TIMEZONE` | `Europe/Berlin` | Timezone of the schedule and default for new clients. |
| `VIGILO_DAILY_RUN_AT` | `06:00` | Daily run (fetch → checks → digest), in `VIGILO_TIMEZONE`. |
| `VIGILO_NOTIFY_CRITICAL_IMMEDIATELY` | `true` | Mail new critical findings found outside the daily run right away. |
| `VIGILO_DIGEST_WHEN_EMPTY` | `true` | Send an "all clear" digest when nothing is open. |
| `VIGILO_FETCH_METRICS_DAYS` | `40` | Days of metrics fetched per run. |
| `VIGILO_HTTP_TIMEOUT` / `VIGILO_HTTP_RETRIES` | `30` / `3` | Outbound HTTP timeout (s) and retries for Google, Meta and lodgely. |
| `VIGILO_RETENTION_FETCH_LOGS_DAYS` | `90` | Fetch logs older than this are deleted daily. |
| `VIGILO_RETENTION_METRICS_DAYS` | `400` | Daily ad metrics. |
| `VIGILO_RETENTION_LEADS_DAYS` | `400` | Lead metadata. |
| `VIGILO_RETENTION_RESOLVED_FINDINGS_DAYS` | `365` | Resolved findings. |

### Google Ads

| Variable | Meaning |
|---|---|
| `VIGILO_GOOGLE_ADS_DEVELOPER_TOKEN` | Developer token from your manager account's API Center. |
| `VIGILO_GOOGLE_ADS_CLIENT_ID` / `VIGILO_GOOGLE_ADS_CLIENT_SECRET` | OAuth client from Google Cloud. |
| `VIGILO_GOOGLE_ADS_REFRESH_TOKEN` | Agency-wide refresh token (a connection can override it). |
| `VIGILO_GOOGLE_ADS_LOGIN_CUSTOMER_ID` | Manager (MCC) id sent as `login-customer-id` (a connection can override it). |
| `VIGILO_GOOGLE_ADS_API_VERSION` | Pinned API version, default `v26`. |

These are the same values as lodgely's `LODGELY_GOOGLE_ADS_*` — copy them over.

### Meta Ads

| Variable | Meaning |
|---|---|
| `VIGILO_META_ADS_ACCESS_TOKEN` | Agency-wide system-user token with `ads_read` (a connection can override it). Same value as lodgely's `LODGELY_META_ADS_ACCESS_TOKEN`. |
| `VIGILO_META_APP_SECRET` | Optional app secret; every call then carries an `appsecret_proof`. |
| `VIGILO_META_ADS_API_VERSION` | Pinned Graph/Marketing API version, default `v26.0`. |
| `VIGILO_META_CONVERSION_ACTION_TYPES` | Which action counts as a conversion; the first type present wins. Default `lead,onsite_conversion.lead_grouped,offsite_conversion.fb_pixel_lead`. |

### lodgely

| Variable | Meaning |
|---|---|
| `VIGILO_LODGELY_ENABLED` | Feature flag, default `false` (see [lodgely integration](#lodgely-integration)). |
| `VIGILO_LODGELY_URL` | Base URL of your lodgely install. |
| `VIGILO_LODGELY_API_TOKEN` | Read-only lodgely API token (lead metadata scope). |

## Connecting Google Ads

vigilo talks to the Google Ads API over REST with GAQL `search` queries only.
Google has no read-only OAuth scope for Ads (`https://www.googleapis.com/auth/adwords`
is the only one); vigilo's client contains no mutate call, and a test
guards that.

1. **Developer token.** Sign in to your Google Ads **manager account
   (MCC)** → *Admin* → *API Center*, accept the terms and copy the developer
   token. A new token only works with test accounts; apply for **Basic
   access** to read real accounts.
2. **Google Cloud project.** In the [Google Cloud console](https://console.cloud.google.com/),
   create (or pick) a project and enable the **Google Ads API**.
3. **OAuth consent screen.** Configure it (*Internal* if you use Google
   Workspace; otherwise *External* and **publish it to production** —
   refresh tokens of apps in *Testing* expire after 7 days).
4. **OAuth client.** *Credentials* → *Create credentials* → *OAuth client ID*
   → type *Web application*, with the authorised redirect URI
   `https://developers.google.com/oauthplayground`. Note client ID and secret.
5. **Refresh token.** Open the [OAuth 2.0 Playground](https://developers.google.com/oauthplayground),
   click the gear icon → *Use your own OAuth credentials* → paste ID and
   secret. Enter the scope `https://www.googleapis.com/auth/adwords`,
   authorise **with the Google user that has access to your MCC**, then
   *Exchange authorization code for tokens* and copy the refresh token.
6. **Configure vigilo.** In `.env`:

   ```dotenv
   VIGILO_GOOGLE_ADS_DEVELOPER_TOKEN=…
   VIGILO_GOOGLE_ADS_CLIENT_ID=….apps.googleusercontent.com
   VIGILO_GOOGLE_ADS_CLIENT_SECRET=…
   VIGILO_GOOGLE_ADS_REFRESH_TOKEN=1//…
   VIGILO_GOOGLE_ADS_LOGIN_CUSTOMER_ID=123-456-7890   # your MCC
   VIGILO_MOCK_MODE=false
   ```

   then `docker compose up -d`.
7. **Add the connection.** *Connections* → *Add connection* → client,
   *Google Ads*, the client's **customer ID** (e.g. `987-654-3210`) → *Save*
   → *Test*. *Fetch now* pulls data immediately; otherwise the daily run does.

A client account that is not under your MCC can still be connected: give it
its own refresh token (and its own manager id, if any) on the connection.

## Connecting Meta Ads

vigilo uses the Marketing API with a **system-user token** and the
`ads_read` permission, GET requests only.

1. **Business portfolio.** In [Meta Business Suite](https://business.facebook.com/)
   → *Settings*, make sure every client ad account is either owned by your
   business or shared with it as a partner.
2. **App.** At [developers.facebook.com](https://developers.facebook.com/apps)
   create an app of type *Business*, add the *Marketing API* product and
   connect the app to your business portfolio.
3. **System user.** *Business settings* → *Users* → *System users* → *Add*
   (role *Employee* is enough). *Assign assets* → *Ad accounts* → select the
   client accounts with the **View performance** permission only.
4. **Token.** On the system user, *Generate new token*, pick your app,
   choose the expiry (*Never* is the simplest; vigilo warns 14 days before a
   60-day token expires), and select only **`ads_read`**.
5. **Configure vigilo.**

   ```dotenv
   VIGILO_META_ADS_ACCESS_TOKEN=EAAB…
   VIGILO_META_APP_SECRET=…        # optional, adds appsecret_proof
   VIGILO_MOCK_MODE=false
   ```

   then `docker compose up -d`.
6. **Add the connection.** *Connections* → *Add connection* → client,
   *Meta Ads*, the ad account id (`act_…` or just the digits) → *Save* →
   *Test*.

## lodgely integration

**Status: available from lodgely 0.59.0, off by default until the
end-to-end test.** vigilo reads a read-only, PII-free lead-metadata feed
with cursor paging and deletions, specified in
[docs/LODGELY-CONTRACT.md](docs/LODGELY-CONTRACT.md). vigilo's client is
covered by contract tests against fixtures and sits behind
`VIGILO_LODGELY_ENABLED=false`.

To connect a lodgely 0.59.0+ install:

1. In lodgely, *Settings → API tokens* → create a token with *Lead metadata*
   access and copy it (shown once).
2. Set `VIGILO_LODGELY_ENABLED=true`, `VIGILO_LODGELY_URL` and
   `VIGILO_LODGELY_API_TOKEN`, then restart.
3. Set each client's lead source to *lodgely* with its client key: the slug
   of the lodgely client name (`Kanzlei Brenner & Partner` →
   `kanzlei-brenner-partner`). *Test* calls the feed with `limit=1`.

In mock mode the feed is simulated. The *spend without leads* check and
lead-based CPL need a lead source; all other checks work without one.
lodgely sends `has_gclid: false` until it stores click ids per lead, so
Google CPL uses platform conversions until then.

## Webhooks

*Notifications* → *Webhooks* adds endpoints. vigilo POSTs JSON:

| Event | When | Body |
|---|---|---|
| `finding.critical` | a critical finding opens, reopens or escalates | `{"event", "sent_at", "vigilo_version", "text", "finding": {…}}` |
| `digest` | after the scheduled daily run | `{"event", "sent_at", "vigilo_version", "text", "summary": {"critical", "warning", "info", "new"}, "findings": [{…}]}` |
| `ping` | *Send test* button | `{"event": "ping", …, "text"}` |

A finding object: `id`, `client {id, name}`, `check`, `severity`, `status`,
`platform`, `title` (English), `title_key`, `title_params`, `dedupe_key`,
`first_detected_at`, `last_detected_at`, `opened_at`, `url`. The top-level
`text` makes the payload postable to a Slack incoming webhook as is.

Every request carries `X-Vigilo-Event`, `X-Vigilo-Delivery` (UUID),
`X-Vigilo-Timestamp` and `X-Vigilo-Signature`. Verify it:

```text
expected = "sha256=" + hex(hmac_sha256(secret, X-Vigilo-Timestamp + "." + raw_body))
reject if expected != X-Vigilo-Signature, or |now − timestamp| > 300 s
```

Failed deliveries are retried twice (after 30 s and 5 min).

## Commands

Always run them inside the container: `docker compose exec app php artisan …`

| Command | What it does |
|---|---|
| `vigilo:run [--client=ID] [--no-fetch] [--digest] [--sync]` | Fetch + checks now (queued; `--sync` inline). Digest only with `--digest` (the scheduled run sends it itself). |
| `vigilo:digest` | Send the digest to everyone who receives it. |
| `vigilo:user:create EMAIL [--role=operator\|viewer] [--name=] [--password=]` | Create a user; prints a generated password. |
| `vigilo:admin:ensure` | Create the first operator from `ADMIN_*` (run by the entrypoint). |
| `vigilo:demo:seed` / `vigilo:demo:remove` | Load / remove the demo clients (mock mode). |
| `vigilo:prune` | Apply the retention windows (runs daily). |
| `vigilo:health:queue` | Queue healthcheck (used by Docker). |
| `vigilo:db:wait` | Wait for the database (used by the entrypoint). |

## Running in production

- Serve over HTTPS: set `VIGILO_SITE_ADDRESS=vigilo.your-agency.example`,
  map `VIGILO_HTTP_PORT=80` and `VIGILO_HTTPS_PORT=443`, then set
  `APP_URL=https://…`, `APP_ENV=production` and `SESSION_SECURE_COOKIE=true`.
  Behind your own reverse proxy, keep `:80` and adjust `TRUSTED_PROXIES`.
- Configure SMTP so digests actually leave the server.
- Back up the `pgdata` volume (e.g. `docker compose exec postgres pg_dump -U vigilo vigilo`)
  **and** `storage/app/.app-key` from the `storage` volume.
- Memory: the idle stack uses ≈ 180 MB, ≈ 210 MB during a run with the demo
  data; it is tuned for a 512 MB VPS (PHP-FPM workers start on demand,
  Postgres with small buffers). Raise `PHP_FPM_MAX_CHILDREN` (default 4) on
  bigger machines.
- Updating: `git pull && docker compose up -d --build`. Migrations run on
  start.
- Smaller image: `docker compose build --build-arg VIGILO_DEV_DEPENDENCIES=false`
  drops the test tooling (then `php artisan test` is not available).

## Privacy & GDPR notes for self-hosters

You run vigilo, so you are the controller for what it processes. In short:

- **Ad data is aggregated.** vigilo stores campaign-level daily metrics
  (spend, impressions, clicks, conversions) and ad metadata (name, campaign,
  review status, policy topics, final URLs). No user-level data from the
  platforms.
- **Lead data is metadata only.** Per lead: an opaque id, submission and
  status-change times, status/outcome, source type, Meta campaign/ad
  set/ad/form ids, a `has_gclid` flag and boolean flags (duplicate,
  qualified, contacted, organic, spam). **No names, email addresses, phone
  numbers, free-text answers, IP addresses or click ids are stored** — the
  table has no column for them and the importer is an allow-list. Lead ids
  still point to people inside lodgely, so treat the metadata as
  pseudonymous personal data.
- **Deletion propagates.** When a lead is deleted in lodgely, the feed sends
  a tombstone and vigilo deletes its row. Retention defaults to 400 days
  (`VIGILO_RETENTION_LEADS_DAYS`) and runs daily.
- **Users.** vigilo stores its users' names, emails, password hashes,
  language and last sign-in.
- **Outbound connections** go only to the Google Ads API, the Meta Graph
  API, your lodgely install, the landing pages being checked (GET, user
  agent `vigilo-linkcheck/…`), your SMTP server and the webhooks you add.
  No telemetry, no CDNs, no external fonts.
- **Secrets at rest.** Per-connection tokens and webhook secrets are
  encrypted with `APP_KEY`. Agency-wide tokens live in `.env`; protect that
  file and the server accordingly.
- **Logs.** `MAIL_MAILER=log` writes digest mails (client names and finding
  titles, no lead data) to `storage/logs/mail.log`.
- Google and Meta are already your processors for the ad accounts; check
  that your hosting provider has a data processing agreement with you.

## Architecture at a glance

```text
app/
  Domain/
    AdPlatforms/   AdPlatform contract, Google (REST+GAQL), Meta (Graph), mocks,
                   ResilientHttp (retry/backoff/rate limits), AdDataSync, FetchLogger
    Leads/         LeadSource contract, LeadMetadata allow-list, lodgely client, mock, LeadSync
    Checks/        Check contract, CheckRegistry, the 7 checks, ClientSnapshot,
                   ThresholdResolver, CheckRunner, LandingPages/ (probers)
    Findings/      FindingReconciler (dedupe/escalate/resolve/reopen), FindingActions
    Notifications/ Notifier, DigestBuilder, WebhookDispatcher (HMAC)
    Runs/          RunManager: batch of [SyncClientData → RunClientChecks] per client, then digests
    Demo/          DemoSeeder
  Jobs/            queue jobs (sync, checks, digests, webhook delivery)
  Livewire/        UI pages
config/vigilo.php  every vigilo setting and the check defaults
docs/              LODGELY-CONTRACT.md
docker/            entrypoint, PHP/FPM config, Caddyfile
```

See [CLAUDE.md](CLAUDE.md) for the maintainer guide (conventions, how to add
a check).

## Development

```bash
composer install && npm ci && npm run build
cp .env.example .env            # point DB_* at a local Postgres 16
php artisan key:generate
php artisan migrate
php artisan vigilo:admin:ensure && php artisan vigilo:demo:seed
php artisan serve               # plus: php artisan queue:work / schedule:work

php artisan test                # Pest (in-memory SQLite)
vendor/bin/pint                 # code style
vendor/bin/phpstan analyse      # Larastan
```

CI runs Pint, Larastan, Pest and a full `docker compose` install with the
test suite inside the container.

## Roadmap

Phase 1 (the watchdog) is this release. Phase 2 — the **lead-quality
miner** (lead quality per Meta ad / ad set and Google keyword from lodgely
outcomes, junk-lead spikes, negative-keyword and expansion suggestions) —
and later phases are in [ROADMAP.md](ROADMAP.md). Changes are listed in
[CHANGELOG.md](CHANGELOG.md). Open tasks and unanswered questions — including
the end-to-end test with lodgely — are tracked in
[docs/OPEN-ITEMS.md](docs/OPEN-ITEMS.md).

## License

vigilo is free software under the [GNU Affero General Public License v3.0
or later](LICENSE). If you run a modified version for others over a
network, you must offer them its source code. Security reports: see
[SECURITY.md](SECURITY.md).

# vigilo Roadmap

vigilo's job: watch every client's ad accounts once a day and tell the
agency what is broken (**watchdog**) and what is wasting money (**miner**),
in one findings inbox and one daily digest. It is not a reporting
dashboard, not a CRM and not an automation engine (see CLAUDE.md →
*Product north star*). It stays **read-only towards ad platforms** until a
phase explicitly decides otherwise.

Target use: B2B lead generation for clients in the DACH region, self-hosted
and GDPR-sensitive. Sibling of lodgely (lead intake), OpenFlow (forms) and
calon (booking).

Effort: **S** ≈ days, **M** ≈ 1–2 weeks, **L** ≈ several weeks.

| Phase | Theme | Items | Status |
|---|---|---|---|
| 1 | Watchdog | VG-1 – VG-6 | shipped in 0.1.0 |
| 2 | Lead-quality miner | VG-7 – VG-12 | planned |
| 3 | Operator comfort | VG-13 – VG-17 | planned |
| 4 | Client portal | VG-18, VG-19 | later |

Cross-repo dependency: **X-V1** (lodgely lead-metadata API, drafted as
lodgely `LG-15` in [docs/LODGELY-CONTRACT.md](docs/LODGELY-CONTRACT.md))
unlocks real lead data for *spend without leads*, lead-based CPL and all of
Phase 2.

---

## Phase 1 — Watchdog (shipped in 0.1.0)

### VG-1 · Foundation and zero-step Docker install
**Status:** done · **Effort:** M
**Acceptance criteria**
- `cp .env.example .env && docker compose up -d` on a fresh clone gives a
  working install: app, caddy, postgres, queue, scheduler, all healthy.
- The entrypoint generates `APP_KEY`, waits for Postgres, migrates and seeds
  the first operator from `ADMIN_EMAIL` / `ADMIN_PASSWORD` (weak/default
  passwords refused; empty = generated and printed once, forced change).
- `docker compose exec app php artisan test` is green and cannot touch the
  live database.
- Runs on ~512 MB; EN + DE, dark/light mode, per-user language.

### VG-2 · Read-only ad platform adapters
**Status:** done · **Effort:** M
**Acceptance criteria**
- `AdPlatform` contract with mock and live adapters for Google Ads (REST +
  GAQL, pinned API version) and Meta Marketing API (system-user token,
  pinned Graph version).
- Retries with backoff, rate-limit handling, a fetch log row for every
  fetch; credentials encrypted at rest.
- Tests prove the adapters only read (no mutate, GET only).

### VG-3 · Lead source contract
**Status:** done on the vigilo side; lodgely API pending (X-V1) · **Effort:** M
**Acceptance criteria**
- `docs/LODGELY-CONTRACT.md` defines a read-only, PII-free change feed per
  client with cursor paging and tombstones.
- `LodgelyLeadSource` behind `VIGILO_LODGELY_ENABLED`, covered by contract
  tests on fixtures; the importer is an allow-list.

### VG-4 · Watchdog checks
**Status:** done · **Effort:** L
**Acceptance criteria**
- Conversion drop, spend without leads, connection health, budget pacing,
  disapproved/limited ads, CPL spike, landing-page health — each with unit
  tests on fixture data and thresholds per install and per client.
- Findings dedupe, escalate, auto-resolve and reopen; missing data never
  resolves a finding.

### VG-5 · Findings inbox and settings UI
**Status:** done · **Effort:** M
**Acceptance criteria**
- Inbox with filters (client, severity, status, check), acknowledge, snooze,
  resolve, history; client list and settings; connections with *Test* and
  *Fetch now*; check settings; users; profile. Viewers are read-only
  server-side.

### VG-6 · Digest, critical alerts and webhooks
**Status:** done · **Effort:** S
**Acceptance criteria**
- Daily digest per user after the scheduled run (critical first, reader's
  language), immediate mail for new critical findings outside it,
  HMAC-signed webhooks usable from n8n/Slack.

---

## Phase 2 — Lead-quality miner

All Phase 2 items are **suggestions**: vigilo raises findings with evidence;
people make the change in the ad platform. Seams already in place:
`LeadMetadata` carries status/outcome, Meta ids and `has_gclid`;
`LeadStatus::outcome()` buckets outcomes (won / lost / junk / in progress /
open); `AdPlatforms\Contracts\ProvidesSearchTerms` is declared.

### VG-7 · Live lodgely lead feed
**Status:** blocked on X-V1 · **Effort:** S · **Depends on:** lodgely LG-15
**Why:** everything in this phase needs real outcomes.
**Acceptance criteria**
- Turning on `VIGILO_LODGELY_ENABLED` against a lodgely that ships contract
  v1 fills lead metadata for every mapped client; contract fixtures are
  refreshed from real (anonymised) responses and both repos' contract
  tests pass.
- Optional `GET /api/v1/clients` powers a client-key dropdown.

### VG-8 · Lead quality per Meta ad and ad set
**Status:** planned · **Effort:** M · **Depends on:** VG-7
**Why:** Meta optimises for form fills; agencies need to know which ads
produce *qualified* leads.
**Acceptance criteria**
- Per ad / ad set over a configurable window: leads, qualified share,
  junk share (duplicate, spam, incomplete), won share, cost per qualified
  lead.
- Findings for ads/ad sets whose qualified share is far below the account
  average with enough volume (thresholds like Phase 1), listing the
  evidence; suggestion text ("reduce budget / review targeting"), no
  automatic change.

### VG-9 · Lead quality per Google keyword
**Status:** planned · **Effort:** L · **Depends on:** VG-7, a decision on click ids
**Why:** the same question for Search: which keywords bring junk.
**Acceptance criteria**
- Decide how to attribute a lead to a keyword without storing click ids in
  vigilo (options: lodgely resolves gclid → keyword via `click_view` and
  sends the keyword/ad group; or vigilo resolves at ingest and discards the
  id). Documented in the contract (v2) and the privacy notes.
- Per keyword: leads, qualified/junk/won share, cost per qualified lead;
  findings for outliers with enough volume.

### VG-10 · Junk-lead share spikes
**Status:** planned · **Effort:** S · **Depends on:** VG-7
**Acceptance criteria**
- Junk share (duplicate / spam / incomplete) over the last 7 days vs. the
  28 days before, per client and platform; warning/critical by factor, with
  the top contributing ads/campaigns in the evidence.

### VG-11 · Negative-keyword and expansion suggestions
**Status:** planned · **Effort:** M
**Acceptance criteria**
- Implement `ProvidesSearchTerms` for Google (`search_term_view`, read-only).
- Suggest negatives: search terms with spend above a threshold and no
  conversions (and, with VG-9, no qualified leads); suggest expansions:
  converting search terms not yet covered by a keyword.
- Suggestions are findings with an exportable list (CSV) — vigilo never
  adds keywords itself.

### VG-12 · Miner section in inbox and digest
**Status:** planned · **Effort:** S
**Acceptance criteria**
- Watchdog vs. miner findings are distinguishable (filter, digest section);
  miner findings default to a weekly cadence so the daily digest stays
  short.

---

## Phase 3 — Operator comfort

### VG-13 · "Connect with Google" OAuth button
**Status:** planned · **Effort:** S
**Acceptance criteria**
- Operators connect a Google user via OAuth consent (adwords scope) instead
  of pasting a refresh token; the token is stored encrypted on the
  connection; works with a public `APP_URL` and documents the redirect URI.

### VG-14 · Intraday light run
**Status:** planned · **Effort:** S
**Acceptance criteria**
- Optional run every N hours for connection health and spend without leads
  only, off by default; critical findings alert immediately.

### VG-15 · Two-factor authentication
**Status:** planned · **Effort:** S
**Acceptance criteria**
- TOTP 2FA for operators with recovery codes (same approach as lodgely).

### VG-16 · SMTP and webhook settings in the UI
**Status:** planned · **Effort:** S
**Acceptance criteria**
- SMTP configured on a settings page (encrypted), overriding `.env`, with a
  test-mail button; picked up by the queue worker without a restart.

### VG-17 · Backups
**Status:** planned · **Effort:** S
**Acceptance criteria**
- Scheduled `pg_dump` into the storage volume with retention, plus restore
  docs; the app key is included (encrypted with a passphrase).

---

## Phase 4 — Client portal

### VG-18 · Client logins
**Status:** later · **Effort:** M
**Acceptance criteria**
- A `client` role scoped to one or more clients through
  `Client::scopeVisibleTo()` (the seam already used by every query); clients
  see their findings, cannot change settings.

### VG-19 · Per-client digest
**Status:** later · **Effort:** S · **Depends on:** VG-18
**Acceptance criteria**
- Optional weekly digest per client login, in plain language, without
  internal notes.

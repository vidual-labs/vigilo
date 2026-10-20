# Open tasks and questions

**State:** vigilo 0.1.0 (Phase 1, the watchdog) is merged into `main`
(PR #1, 2026-10-20). CI is green, and `cp .env.example .env && docker
compose up -d` works on a fresh clone. This file lists everything that is
still open: tasks, unanswered questions and known gaps.

**Next session:** lodgely and vigilo together. Start with
[§1 Cross-repo tasks](#1-cross-repo-tasks-vigilo--lodgely) and answer the
questions marked **cross-repo** in [§2](#2-open-questions) first. They
decide the lodgely API vigilo depends on. The contract itself is
[LODGELY-CONTRACT.md](LODGELY-CONTRACT.md).

Keep this file current. Close an item by deleting it and noting the outcome
in CHANGELOG / ROADMAP, or move a decided question into the table below.

---

## Decisions already made

Do not reopen these without the owner (Richard).

| Decision | Where it shows |
|---|---|
| **Ad-platform credentials come from `.env` in each tool.** vigilo's `VIGILO_GOOGLE_ADS_*` / `VIGILO_META_ADS_ACCESS_TOKEN` mirror lodgely's `LODGELY_*` names so the values can be copied. lodgely does **not** hand out tokens over an API. | README → configuration; LODGELY-CONTRACT → "Not proposed" |
| **Leads carry `has_gclid` only.** No click id (gclid/gbraid/wbraid/fbclid) is ever sent to or stored by vigilo. | `LeadMetadata`, contract, privacy notes |
| **Client key in contract v1 = slug of lodgely's `client_name`.** (May be revisited with lodgely LG-4; see Q-2.) | Contract → "Client identity" |
| **One full run per day** (default 06:00 Europe/Berlin). No intraday run in v1 (that is VG-14). | `VIGILO_DAILY_RUN_AT` |
| **Default UI language English**; German complete; per-user language. German uses the formal "Sie". | `APP_LOCALE`, `lang/de.json` |
| **First admin password:** generated when `ADMIN_PASSWORD` is empty, printed once, must be changed at first login. A weak or default password stops the start. | `vigilo:admin:ensure` |
| **v1 is read-only towards ad platforms; no lead PII; landing pages GET only.** | CLAUDE.md → Hard rules |
| **License AGPL-3.0-or-later.** lodgely stays GPL-3.0-only; the two only talk over HTTP. | LICENSE |

---

## 1. Cross-repo tasks (vigilo ↔ lodgely)

### T-L1 · Build the lead-metadata read API in lodgely (lodgely LG-15)
**Repo:** lodgely · **Effort:** M · **Blocks:** everything lead-based in vigilo (spend without leads with real data, lead-based CPL, all of Phase 2)

Implement contract v1 as specified in [LODGELY-CONTRACT.md](LODGELY-CONTRACT.md):
- `GET /api/v1/lead-metadata`: change feed ordered by `(updated_at, id)`, opaque cursor, `has_more`, tombstones.
- Read-only token scope `lead-metadata:read`; tokens stored hashed and shown once.
- Rate limit 60/min.
- A lodgely contract test that fails if PII keys appear in the payload.

The drafted acceptance criteria are at the end of the contract doc.

Two dependencies inside lodgely:
- `submitted_at` exists only after **LG-3**; until then use `created_at`.
- `has_gclid` needs attribution stored per lead, which comes with **LG-6** (currently partial: OpenFlow click ids sit in `raw_payload`). Decide whether v1 derives it from `raw_payload` or waits for LG-6 (Q-3).

### T-L2 · Put the item into lodgely's ROADMAP
**Repo:** lodgely · **Effort:** S
- Add **LG-15** (text drafted at the end of LODGELY-CONTRACT.md).
- lodgely's ROADMAP has a *Shared: cross-repo interfaces* section that is duplicated verbatim with OpenFlow and covers only OpenFlow ↔ lodgely. Decide whether the vigilo interface gets a shared item there (e.g. **X-3**) or a separate "vigilo" section (Q-1).
- Add vigilo as a consumer to **X-2** (cross-system erasure): erasures in lodgely must reach vigilo as feed tombstones (vigilo already deletes on tombstones).

### T-L3 · Align client identity with lodgely LG-4
**Repo:** both · **Effort:** S · **Depends on:** Q-2
lodgely plans a real Client model (LG-4, decision D4). When it lands, the contract's `client` parameter should take that model's stable key. vigilo needs no code change, only the key entered per client. Optionally add `GET /api/v1/clients` (contract → "Optional, later") so vigilo can offer a dropdown instead of a text field. That needs a small vigilo UI change in `ClientEdit`.

### T-L4 · End-to-end test with both stacks
**Repo:** both · **Effort:** S · **Depends on:** T-L1
1. Run lodgely and vigilo side by side in Docker.
2. Create a read-only token in lodgely and set `VIGILO_LODGELY_ENABLED=true`, `VIGILO_LODGELY_URL`, `VIGILO_LODGELY_API_TOKEN`.
3. Map a client key and run `vigilo:run --sync`.

Then:
- Record real, anonymised responses into `tests/Fixtures/lodgely/` and keep both contract test suites green.
- Check that a lead deleted in lodgely disappears from vigilo.
- Check that no PII column fills up.

### T-L5 · Document the pairing in lodgely's README
**Repo:** lodgely · **Effort:** S
- A short "vigilo" section: what the token is for, that the feed is PII-free, and how to create the token.
- The env-variable mapping `LODGELY_GOOGLE_ADS_*` → `VIGILO_GOOGLE_ADS_*`, `LODGELY_META_ADS_ACCESS_TOKEN` → `VIGILO_META_ADS_ACCESS_TOKEN`.

### T-L6 · Switch the lodgely integration on by default
**Repo:** vigilo · **Effort:** S · **Depends on:** a lodgely release with T-L1
Keep `VIGILO_LODGELY_ENABLED=false` until a lodgely release ships the API. Then decide whether the default flips, and update README → *lodgely integration* and CHANGELOG.

---

## 2. Open questions

Each question has my recommendation; the owner decides.

### Q-1 · Where does the vigilo ↔ lodgely interface live in lodgely's roadmap? *(cross-repo)*
lodgely's shared section is an OpenFlow ↔ lodgely document duplicated in both repos.
**Options:** (a) a new shared item X-3 in that section (then it is duplicated into OpenFlow too, which does not care); (b) a separate "Shared with vigilo" section in lodgely's ROADMAP mirrored in vigilo's ROADMAP; (c) only LG-15 plus a link to vigilo's contract doc.
**Recommendation:** (b). It keeps the OpenFlow document untouched and gives vigilo ↔ lodgely its own mirrored section.

### Q-2 · Client key: slug of `client_name` now, or wait for lodgely LG-4? *(cross-repo)*
A slug changes when someone renames the client in lodgely, and the feed then 404s until the key is updated in vigilo.
**Options:** (a) slug now and the LG-4 key later, with vigilo showing *Connection health* when the key breaks (already the case); (b) build LG-4 first and ship the API with stable client ids.
**Recommendation:** (a) unless LG-4 is close; the failure mode is visible and cheap to fix.

### Q-3 · Where does `has_gclid` come from before LG-6? *(cross-repo)*
**Options:** (a) lodgely derives it from `raw_payload` (OpenFlow hidden fields) in the API layer; (b) LG-15 ships with `has_gclid: false` until LG-6.
**Recommendation:** (a) if `raw_payload` reliably carries `gclid` / `gbraid` / `wbraid`; otherwise (b), with lead-based CPL for Google falling back to platform conversions (vigilo already does this automatically).

### Q-4 · Who resolves gclid → keyword for Phase 2 (VG-9)? *(cross-repo)*
vigilo must not store click ids, but the keyword-quality miner needs keyword attribution.
**Options:** (a) lodgely resolves gclid → keyword / ad group via Google Ads `click_view` and sends `google_keyword` / `google_ad_group_id` in a contract v2; (b) vigilo resolves at ingest from a one-time gclid in the feed and drops it immediately; (c) skip keyword-level quality and stay at campaign level.
**Recommendation:** (a). lodgely already owns attribution (LG-6) and quality conversions (LG-7, decision D5), and it keeps click ids out of vigilo entirely. Needs lodgely to call `click_view` (read-only) within its 90-day window.

### Q-5 · Boundary between vigilo's miner and lodgely's reporting / quality signals *(cross-repo)*
lodgely's ownership table assigns "reporting" and "ad-platform *quality* signals" to lodgely. vigilo's Phase 2 (lead quality per ad / ad set / keyword, junk-lead spikes) analyses the same outcomes.
**Proposed boundary:**
- lodgely *reports* (charts, client reports) and *sends* quality conversions to the platforms.
- vigilo *alerts* (findings: "this ad set produces mostly junk") and *suggests* (negative keywords), without charts.

Confirm, and write it into both roadmaps.

### Q-6 · Should vigilo read spend from lodgely instead of fetching it again? *(cross-repo)*
Both tools now fetch Google/Meta data with the same credentials: lodgely for reporting, vigilo for checks.
**Recommendation:** keep fetching in vigilo. It needs data lodgely does not keep (ads with review status and final URLs, token expiry, account status, fetch health), runs at its own time, and must work without lodgely. Revisit only if API quota becomes a problem.

### Q-7 · Google Ads developer token access level
Real accounts can only be read with **Basic** (or Standard) access; a fresh token is test-only.
**Question:** does the agency MCC token already have Basic access (lodgely uses it)? If not, apply before going live.

### Q-8 · Real Google Ads / Meta accounts for a live test
The live adapters are tested only against fixtures built from the API documentation (see §3).
**Ask:** read access to one real Google Ads customer (through the MCC) and one Meta ad account, to run *Test* and *Fetch now* and record anonymised fixtures.

### Q-9 · Immediate critical mails during the scheduled run
Today the 06:00 run does not send immediate critical mails, because the digest follows minutes later. Webhooks still fire. Manual runs and *Fetch now* mail right away.
**Recommendation:** keep it. Confirm.

### Q-10 · Docker image with or without test tooling
The image ships Pest/Pint/Larastan so `docker compose exec app php artisan test` works (a stated requirement). Without them `vendor/` shrinks from about 167 MB to about 49 MB.
**Recommendation:** keep the current default (`VIGILO_DEV_DEPENDENCIES=true`), document the slim build arg (done), and publish a slim image if and when images go to a registry (Q-12).

### Q-11 · Tag v0.1.0
CHANGELOG already has `[0.1.0] - 2026-10-20`.
**Question:** tag `v0.1.0` on `main` and create a GitHub release from the changelog?

### Q-12 · Publish Docker images?
Today `docker compose up -d` builds locally, which takes a few minutes on a small VPS.
**Option:** a release workflow that pushes `ghcr.io/vidual-labs/vigilo-app` and `-web`, with compose using them by default and `build:` as a fallback.
**Question:** wanted for 0.2?

### Q-13 · Defaults that need real-world calibration
All check thresholds are educated guesses (see `config/vigilo.php`). Two to watch first:
- **Google conversion lag.** Conversions are attributed to the click date and keep arriving for days. At 06:00, "yesterday" is often incomplete, so *conversion drop* (1-day window) may raise false alarms. Options: skip yesterday for Google and use days 2–4, or raise `min_expected_conversions`. Decide after a few weeks of real data.
- **`VIGILO_META_CONVERSION_ACTION_TYPES`.** Defaults to `lead`, then `onsite_conversion.lead_grouped`, then the pixel lead. Accounts optimising for custom conversions need their own action type (e.g. `offsite_conversion.custom.<id>`). Should this be per connection?

---

## 3. vigilo tasks

### Verification gaps (before going live)
- **Google Ads live adapter.** Field names and query shapes follow the v26 documentation but have not been run against a real account. Verify especially:
  - the `asset_group.final_urls` query for Performance Max;
  - `policy_summary.policy_topic_entries`;
  - paging with `nextPageToken`;
  - error payloads.
- **Meta live adapter.** Verify:
  - the `effective_status` filter format on `/ads`;
  - nested `campaign{…}`/`adset{…}` fields;
  - `debug_token` with a system-user token inspecting itself (no app token);
  - throttling headers.
- **Landing-page prober** against real sites: redirects, TLS expiry read with peer verification off, sites that block unknown user agents or HEAD-like behaviour.
- **SMTP delivery** of the digest with a real mail server (only log/array mailers were exercised).

### Known limitations / risks
- **Meta insights** are fetched synchronously (40 days × campaigns, `time_increment=1`). Very large accounts may need Meta's async report jobs.
- **Landing pages** are probed sequentially (max 50 URLs per client, 10 s timeout each). A slow client run can take minutes; the job timeout is 900 s.
- **Spend without leads** works on daily spend data, so its "last N hours" window is approximate (complete days plus today so far).
- **Google** has no read-only OAuth scope; vigilo's read-only guarantee is in code (a test asserts no `mutate`), not in the token.
- **Webhook and monitored URLs** are entered by operators and fetched from the server (SSRF surface, documented in SECURITY.md). Restrict egress if that matters.

### Release and operations
- Q-11 (tag), Q-12 (registry images).
- Backups are documentation only (VG-17).

### Roadmap
Phase 2 (miner, VG-7 – VG-12), Phase 3 (operator comfort, VG-13 – VG-17) and Phase 4 (client portal, VG-18 – VG-19), with acceptance criteria, are in [ROADMAP.md](../ROADMAP.md). VG-7 is blocked on T-L1; VG-9 on Q-4.

---

## 4. Working in a Claude Code cloud session

Notes from building 0.1.0 in a sandbox. They are not needed on a normal machine.

- **Docker** is installed, but the daemon is not running: start it with `dockerd &`.
- **Docker Hub returns 429.** Add `{"registry-mirrors":["https://mirror.gcr.io"]}` to `/etc/docker/daemon.json` and restart the daemon.
- **TLS interception.** Outbound HTTPS goes through an intercepting proxy, so `apk` / `composer` / `npm` inside image builds fail TLS. For local builds, re-tag the base images (`php:8.4-fpm-alpine`, `php:8.4-cli-alpine`, `caddy:2-alpine`, `node:22-alpine`) with the proxy CA from `/root/.ccr/ca-bundle.crt` appended to `/etc/ssl/certs/ca-certificates.crt`, and set `NODE_EXTRA_CA_CERTS`.
- **Never commit that workaround.** The repository Dockerfile must stay clean. It has no `# syntax=` line, so BuildKit uses the local (patched) base images.
- **PHP version.** The host has PHP 8.3, but the lock file needs 8.4. Run composer, Pest, Pint and Larastan in a `php:8.4-cli-alpine` container with the repo mounted. The container needs `icu-data-full` for German formatting tests.
- **Tests without a `.env`** pass but print Dotenv warnings; `touch .env` first (the Docker image ships an empty one).
- **Visual checks:** Playwright with the preinstalled Chromium (`/opt/pw-browsers`) against `http://localhost:8080`.

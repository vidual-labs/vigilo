# Open tasks and questions

**State:** vigilo 0.1.0 (Phase 1, the watchdog) is merged into `main`
(PR #1, 2026-10-20). CI is green, and `cp .env.example .env && docker
compose up -d` works on a fresh clone. lodgely 0.59.0 ships the
lead-metadata API vigilo reads (**LG-15**, contract v1); lodgely 0.60.0 adds
the Google ValueTrack id fields. This file lists everything that is still
open: tasks, unanswered questions and known gaps.

**Next session:** run both stacks together (T-L4), with the real ad accounts
from Q-7 / Q-8 in the same session. The contract is
[LODGELY-CONTRACT.md](LODGELY-CONTRACT.md); the shared roadmap section is
*Shared with vigilo* at the end of [ROADMAP.md](../ROADMAP.md), mirrored in
lodgely's ROADMAP.

Keep this file current. Close an item by deleting it and noting the outcome
in CHANGELOG / ROADMAP, or move a decided question into the table below.

---

## Decisions already made

Do not reopen these without the owner (Richard).

| Decision | Where it shows |
|---|---|
| **Ad-platform credentials come from `.env` in each tool.** vigilo's `VIGILO_GOOGLE_ADS_*` / `VIGILO_META_ADS_ACCESS_TOKEN` mirror lodgely's `LODGELY_*` names so the values can be copied. lodgely does **not** hand out tokens over an API. | README → configuration; LODGELY-CONTRACT → "Not proposed" |
| **Leads carry `has_gclid` only.** No click id (gclid/gbraid/wbraid/fbclid) is ever sent to or stored by vigilo. | `LeadMetadata`, contract, privacy notes |
| **Client key in contract v1 = slug of lodgely's `client_name`** until lodgely LG-4 ships a Client model (Q-2 a, confirmed 2026-10-20). A rename in lodgely breaks the key; *connection health* shows it. | Contract → "Client identity"; T-L3 |
| **The vigilo ↔ lodgely interface has its own section, mirrored in both ROADMAPs** (Q-1 b, confirmed 2026-10-20). The OpenFlow ↔ lodgely section stays untouched. | ROADMAP → *Shared with vigilo* (both repos) |
| **`has_gclid` is `false` until lodgely LG-6** stores click ids per lead (Q-3 b, confirmed 2026-10-20). Google CPL falls back to platform conversions until then. | Contract → lead object; lodgely `LeadMetadataFeed` |
| **Google keyword attribution comes from ValueTrack ids in the final URL suffix, not `click_view`** (Q-4, confirmed 2026-11-04; shared V5). The feed sends `google_campaign_id` / `google_ad_group_id` / `google_criterion_id` as numeric ids only. | Contract → lead object; `LeadMetadata`; ROADMAP → VG-9 |
| **Boundary (Q-5, confirmed 2026-11-04; shared V6):** lodgely *reports* and *sends* quality conversions; vigilo *alerts* and *suggests*, without charts. Tie-break: chart or client-facing → lodgely; threshold + action → vigilo. Both tools count leads the same way: no duplicates (status or flag), no incomplete leads. | ROADMAP → *Shared with vigilo*; `Lead::isCountable()` |
| **vigilo keeps fetching its own ad data** (Q-6, confirmed 2026-11-04; shared V7). Revisit only if fetch logs show Meta throttling. | — |
| **The lodgely integration has no on/off flag:** it is on once `VIGILO_LODGELY_URL` and `VIGILO_LODGELY_API_TOKEN` are set (T-L6, 2026-11-04). | `LodgelyLeadSource::configured()` |
| **The scheduled run sends no immediate critical mails** (the digest follows minutes later); webhooks still fire (Q-9, confirmed 2026-11-04). | CLAUDE.md → Gotchas |
| **The Docker image keeps the test tooling** by default (`VIGILO_DEV_DEPENDENCIES=true`); a slim image only once images are published (Q-10, confirmed 2026-11-04). | Dockerfile |
| **No published Docker images for 0.2** (Q-12, 2026-11-04). | — |
| **Tag releases:** `v0.1.0` on `main` with a GitHub release from the changelog (Q-11, 2026-11-04). | §3 → Release and operations |
| **Google conversion lag:** *conversion drop* skips the most recent day for Google (`google_skip_recent_days`, default 1) (Q-13, 2026-11-04). | `config/vigilo.php`; check settings |
| **Meta conversion action types can be set per connection**, with `VIGILO_META_CONVERSION_ACTION_TYPES` as the default (Q-13, 2026-11-04). | *Connections* form |
| **One full run per day** (default 06:00 Europe/Berlin). No intraday run in v1 (that is VG-14). | `VIGILO_DAILY_RUN_AT` |
| **Default UI language English**; German complete; per-user language. German uses the formal "Sie". | `APP_LOCALE`, `lang/de.json` |
| **First admin password:** generated when `ADMIN_PASSWORD` is empty, printed once, must be changed at first login. A weak or default password stops the start. | `vigilo:admin:ensure` |
| **v1 is read-only towards ad platforms; no lead PII; landing pages GET only.** | CLAUDE.md → Hard rules |
| **License AGPL-3.0-or-later.** lodgely stays GPL-3.0-only; the two only talk over HTTP. | LICENSE |

---

## 1. Cross-repo tasks (vigilo ↔ lodgely)

Done on 2026-10-20 (lodgely 0.59.0): **T-L1** (LG-15, the read API with
hashed, scoped tokens, rate limit, request log and a PII contract test),
**T-L2** (LG-15 and the *Shared with vigilo* section in lodgely's ROADMAP;
erasures reach vigilo as tombstones, noted under LG-11 / LG-12 rather than
in the OpenFlow-shared X-2) and **T-L5** (lodgely README → *Pairing with
vigilo*, including the env mapping).
Done on 2026-11-04: **T-L6** (no on/off flag any more, see the decisions
table).

### T-L3 · Align client identity with lodgely LG-4
**Repo:** both · **Effort:** S · **Depends on:** lodgely LG-4
lodgely plans a real Client model (LG-4, decision D4). When it lands, the contract's `client` parameter should take that model's stable key. vigilo needs no code change, only the key entered per client. Optionally add `GET /api/v1/clients` (contract → "Optional, later") so vigilo can offer a dropdown instead of a text field. That needs a small vigilo UI change in `ClientEdit`.

### T-L4 · End-to-end test with both stacks
**Repo:** both · **Effort:** S · **Depends on:** lodgely 0.60.0
1. Run lodgely and vigilo side by side in Docker.
2. Create a read-only token in lodgely (*Settings → API tokens*) and set `VIGILO_LODGELY_URL` and `VIGILO_LODGELY_API_TOKEN`.
3. Map a client key and run `vigilo:run --sync`.

Then:
- Record real, anonymised responses into `tests/Fixtures/lodgely/` and keep both contract test suites green.
- Check that a lead deleted in lodgely disappears from vigilo.
- Check that no PII column fills up.
- Check a lead whose client is renamed in lodgely: the old key 404s and
  *connection health* raises a finding (Q-2 a). lodgely has no UI to move a
  lead to another client; LG-4 will keep old slugs as aliases and tombstone
  moved leads (shared ROADMAP, follow-ups).
- Compare lead counts: vigilo's countable leads for a client and month must
  match lodgely's *Lodgely leads* KPI for the same range (shared V6). Mind
  the day boundaries: lodgely counts by `created_at` in its `APP_TIMEZONE`
  (default UTC), vigilo by `submitted_at` in the client's timezone, so leads
  from the first and last hours of a month can land in different months.
- Replace the hand-written fixtures' cursor (`{"u": ISO, "i": "ld_…"}`) with a
  real one: lodgely's is `{"u": "Y-m-d H:i:s", "i": <int>}`, base64url
  without padding. Opaque to vigilo, so nothing breaks; the fixtures just
  should look like the real thing.

### T-L7 · A lodgely client without leads answers 404
**Repo:** both · **Effort:** S · **Needs a decision (contract)**
Until LG-4, lodgely only knows a client key once a lead with that
`client_name` exists. A newly onboarded client answers `404 Unknown client`,
so vigilo never gets a successful fetch: *spend without leads* cannot fire
for exactly the case it is for (ads live, form broken from day one), and
*connection health* only reports "no successful fetch" after 26 hours.
Options: (a) wait for LG-4 and a client list; (b) lodgely answers `200` with
`data: []` for a well-formed key it does not know yet (loses typo
detection); (c) vigilo raises a dedicated "lodgely does not know this
client key" finding at once on a `404`, without waiting for the stale
threshold (vigilo only, no contract change).

### T-L8 · Same-second updates can slip past the cursor
**Repo:** lodgely · **Effort:** S
lodgely's cursor is `(updated_at, id)` with second resolution. A lead
updated in the same second as the last item of a page, but with a lower id,
is behind the cursor and never sent (until it changes again); the same goes
for a write whose transaction commits after a read that already covered its
`updated_at`. Rare with daily fetches. Fix on lodgely's side, e.g. only serve
rows with `updated_at` older than the current second, or let the cursor lag
by a few seconds.

---

## 2. Open questions

Q-1 – Q-3 were decided on 2026-10-20, Q-4 – Q-6 and Q-9 – Q-13 on
2026-11-04 (see the table above). Q-7 and Q-8 need the owner to act;
Q-14 and Q-15 (found while testing on 2026-11-05) need a decision.

### Q-7 · Google Ads developer token access level
Real accounts can only be read with **Basic** (or Standard) access; a fresh token is test-only.
**Question:** does the agency MCC token already have Basic access (lodgely uses it)? If not, apply before going live.

### Q-8 · Real Google Ads / Meta accounts for a live test
The live adapters are tested only against fixtures built from the API documentation (see §3).
**Ask:** read access to one real Google Ads customer (through the MCC) and one Meta ad account, to run *Test* and *Fetch now* and record anonymised fixtures. Best done in the same session as T-L4.

### Q-14 · Do organic Meta leads count towards Meta CPL?
vigilo attributes a lead to Meta when it has a Meta id **or** `source_type`
`meta_lead_ads`, and ignores `flags.organic`. Organic Lead Ads leads (no ad
id) therefore lower Meta's cost per lead; lodgely's reporting attributes by
`campaign_id` only. **Question:** exclude `flags.organic` from platform
attribution in *CPL spike* (vigilo only, no contract change)? Related to V6.

### Q-15 · Should viewers see webhook URLs?
*Notifications* shows every webhook URL to viewers too. A Slack or n8n URL
often *is* the secret (anyone who has it can post). **Question:** mask the
URL for viewers (show host only), or keep "viewers see everything"?

### Threshold calibration (ongoing)
All check thresholds are educated guesses (see `config/vigilo.php`). Review them after a few weeks of real data, starting with `google_skip_recent_days` and `min_expected_conversions` for *conversion drop*.

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
- **Tag `v0.1.0`** on `main` and create a GitHub release from the changelog (Q-11, decided 2026-11-04, not done yet). lodgely: tag `v0.59.0` / `v0.60.0` the same way.
- Backups are documentation only (VG-17).

### Roadmap
Phase 2 (miner, VG-7 – VG-12), Phase 3 (operator comfort, VG-13 – VG-17) and Phase 4 (client portal, VG-18 – VG-19), with acceptance criteria, are in [ROADMAP.md](../ROADMAP.md). VG-7 waits on T-L4; VG-9 on Q-4.

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

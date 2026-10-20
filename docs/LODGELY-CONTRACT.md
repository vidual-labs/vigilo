# lodgely → vigilo: lead-metadata contract v1

**Status:** implemented on both sides. vigilo's client is
`App\Domain\Leads\Lodgely\LodgelyLeadSource` (behind
`VIGILO_LODGELY_ENABLED`, tested against `tests/Fixtures/lodgely/`).
lodgely serves it from 0.59.0 as **LG-15** (`LeadMetadataFeed`,
`LeadMetadataController`, contract test `LeadMetadataApiTest`). What lodgely
sends today, where it is narrower than the contract allows, is listed in
[lodgely 0.59.0 specifics](#lodgely-0590-specifics). Open questions that
may extend it (keyword attribution for Phase 2) are in
[OPEN-ITEMS.md](OPEN-ITEMS.md); the shared roadmap section is *Shared with
vigilo* in both repos' ROADMAP.md.

## Why vigilo needs it

Two watchdog checks need to know when real leads arrive, independently of
what the ad platforms report:

- **Spend without leads** — ads spend, but no lead has reached lodgely for N
  hours (broken form, webhook or sync).
- **CPL spike** — cost per lead per platform from leads that lodgely
  attributes to Meta (ad ids) or Google (a click id was present).

Phase 2 (the lead-quality miner) adds outcomes: which Meta ad / ad set and
which Google keyword bring leads that turn into customers, and which bring
junk.

## Principles

1. **Metadata only.** The feed never contains a name, email address, phone
   number, address, IP address, free-text answer, file, AI summary or note.
   It is a separate endpoint from anything that returns lead content, so
   lodgely can guarantee this structurally, not by filtering.
2. **No click ids.** lodgely sends `has_gclid: true|false`, never the gclid
   itself (or fbclid, gbraid, wbraid).
3. **Read-only token, own scope.** A token that can read lead metadata and
   nothing else.
4. **Change feed, not a snapshot.** The cursor runs over *changes*, so
   status updates on old leads (the outcome) and deletions reach vigilo.
5. **Additive evolution.** New optional fields may appear at any time;
   removing or changing a field is a new contract version.

## Authentication

```http
GET /api/v1/lead-metadata?client=kanzlei-brenner HTTP/1.1
Host: leads.agency.example
Authorization: Bearer <read-only token>
Accept: application/json
X-Vigilo-Contract: 1
```

- The token is created in lodgely (e.g. *Settings → API tokens*) with the
  scope `lead-metadata:read`. It is stored in vigilo as
  `VIGILO_LODGELY_API_TOKEN`.
- lodgely answers `401` for a missing/unknown/revoked token and `403` for a
  token without the scope. vigilo treats both as an authentication failure
  (the *connection health* check raises a critical finding).

## Client identity

lodgely has no client entity yet: a client is the free-text `client_name`
on a lead. The contract identifies a client by a **client key**:

- **v1:** the key is the slug of `client_name` (`"Kanzlei Brenner & Partner"`
  → `kanzlei-brenner-partner`), computed by lodgely with the same rules as
  Laravel's `Str::slug()`. lodgely may additionally accept an explicit key
  configured per client later; vigilo does not care how the key is derived.
- The key is entered once per client in vigilo (*Client settings → Lead
  source → lodgely client key*).
- Unknown key → `404` with `{"message": "Unknown client."}`.

## `GET /api/v1/lead-metadata`

### Query parameters

| Parameter | Required | Meaning |
|---|---|---|
| `client` | yes | Client key. |
| `cursor` | no | Opaque cursor from a previous response. Omit on the first request. |
| `updated_since` | no | ISO 8601 timestamp; only used when `cursor` is absent. vigilo sends *now − 60 days* on its first fetch. Without both, lodgely starts at the oldest lead it still holds. |
| `limit` | no | Page size, 1–500, default 500. vigilo asks for 500 (and `1` for *Test connection*). |

### Ordering and cursor semantics

- Items are ordered by `(updated_at, id)` ascending, where `updated_at`
  changes whenever any field of this contract changes (status, flags,
  attribution) or the lead is deleted.
- `next_cursor` encodes the position after the last item of the page; it is
  opaque to vigilo (base64 JSON of `updated_at` + `id` works well).
- Re-reading from an older cursor is safe: vigilo upserts by `id`.
- `has_more: true` means another page is available right now. vigilo fetches
  up to 40 pages per run and resumes from the stored cursor next time.
- When there are no changes, lodgely returns `data: []`, the same
  `next_cursor` it was given, and `has_more: false`.

### Response

```json
{
  "contract_version": 1,
  "client": "alpenblick-solar",
  "data": [
    {
      "id": "ld_01J9Z6Q4K8",
      "submitted_at": "2026-10-19T08:15:00Z",
      "updated_at": "2026-10-20T07:00:00Z",
      "status": "successful",
      "status_changed_at": "2026-10-20T07:00:00Z",
      "source_type": "meta_lead_ads",
      "attribution": {
        "meta_campaign_id": "2385011",
        "meta_adset_id": "23850119",
        "meta_ad_id": "2385011002",
        "meta_form_id": "9001",
        "has_gclid": false,
        "utm_source": null,
        "utm_medium": null,
        "utm_campaign": null
      },
      "flags": {
        "duplicate": false,
        "qualified": true,
        "contacted": true,
        "organic": false,
        "spam": false
      }
    },
    { "id": "ld_01J9Z7B2M1", "deleted": true, "updated_at": "2026-10-20T07:05:00Z" }
  ],
  "next_cursor": "eyJ1IjoiMjAyNi0xMC0yMFQwNzowNTowMFoiLCJpIjoibGRfMDFKOVo3QjJNMSJ9",
  "has_more": false
}
```

### Lead object

| Field | Type | Required | Meaning |
|---|---|---|---|
| `id` | string, `[A-Za-z0-9_\-:.]{1,128}` | yes | Stable, opaque lead id. Must not encode personal data. |
| `submitted_at` | ISO 8601 with offset | yes | When the person enquired (lodgely LG-3); `created_at` until that exists. |
| `updated_at` | ISO 8601 with offset | yes | Last change of any contract field; drives the cursor. |
| `status` | string | yes | lodgely `LeadStatus` value: `new`, `reviewed`, `incomplete`, `duplicate`, `pending`, `offer_sent`, `successful`, `declined`, `no_reply`, `forwarded`. Unknown values are stored as `unknown`. |
| `status_changed_at` | ISO 8601 or null | no | When `status` last changed. |
| `source_type` | string `[a-z0-9_]{1,40}` or null | no | lodgely source key: `csv`, `email`, `webhook`, `manual`, `google_sheets`, `meta_lead_ads`, `openflow`, … |
| `attribution.meta_campaign_id` / `meta_adset_id` / `meta_ad_id` / `meta_form_id` | string or null | no | Meta ids (Lead Ads, or from URL parameters). |
| `attribution.has_gclid` | bool | no | A Google click id (gclid/gbraid/wbraid) was present. The id itself is **never** sent. |
| `attribution.utm_source` / `utm_medium` / `utm_campaign` | string or null | no | Reserved for Phase 2 (campaign matching); vigilo v1 ignores them. |
| `flags.duplicate` / `qualified` / `contacted` / `organic` / `spam` | bool | no | Enrichment flags. `qualified` = `qualified_at` set; `contacted` = called or mailed. |
| `deleted` | `true` | — | Tombstone: the lead was deleted in lodgely (e.g. GDPR erasure, retention purge). Only `id` and `updated_at` accompany it. vigilo deletes its row. |

vigilo **ignores every other field**. Its importer is an allow-list
(`App\Domain\Leads\Data\LeadMetadata`), and its database has no column that
could hold personal data — but lodgely must not send such fields in the
first place.

### Errors

| Status | Meaning | vigilo |
|---|---|---|
| `400` / `422` | Invalid parameter (bad cursor, limit). Body `{"message": "…"}`. | Records a failure, keeps the cursor. |
| `401` / `403` | Token missing, invalid, revoked, or lacking the scope. | *Authentication failed* → critical finding. |
| `404` | Unknown client key. | Failure; the operator fixes the key. |
| `429` | Rate limited; `Retry-After` in seconds. | Waits (up to 60 s) and retries, up to 3 times. |
| `5xx` | Server error. | Retries with backoff (1 s, 3 s, 9 s). |

A response whose `contract_version` is not `1` is rejected without storing
anything.

### Limits

- vigilo fetches once a day per client (plus manual *Fetch now*), so the
  load is tiny. lodgely may rate-limit at e.g. 60 requests per minute per
  token.
- `Test connection` calls the same endpoint with `limit=1`.

## Optional, later: `GET /api/v1/clients`

Lists the client keys a token can read, so vigilo can offer a dropdown
instead of a text field:

```json
{"contract_version": 1, "data": [{"key": "kanzlei-brenner-partner", "name": "Kanzlei Brenner & Partner", "lead_count": 312}]}
```

**Not proposed:** an endpoint that hands ad-platform tokens from lodgely to
vigilo. Both tools read the same agency-wide credentials from their own
environment (`LODGELY_GOOGLE_ADS_*` ↔ `VIGILO_GOOGLE_ADS_*`,
`LODGELY_META_ADS_ACCESS_TOKEN` ↔ `VIGILO_META_ADS_ACCESS_TOKEN`); copying
the values is simpler and keeps refresh tokens off the wire. A future,
token-free endpoint could share the *mapping* of clients to ad account ids.

## Testing both sides

- vigilo: `tests/Feature/Adapters/LodgelyContractTest.php` runs the client
  against `tests/Fixtures/lodgely/page1.json` and `page2.json` (paging,
  status updates, a tombstone, PII in the payload that must be dropped,
  version mismatch, auth failure).
- lodgely: `tests/Feature/LeadMetadataApiTest.php` asserts the contract
  shape, paging, tombstones, auth, rate limiting, and that the response
  **contains none of** `full_name`, `email`, `phone`, `message`,
  `custom_answers`, `raw_payload`, `gclid` (and more), neither as keys nor
  as values.
- Next (OPEN-ITEMS T-L4): record real (anonymised) responses from lodgely
  into vigilo's fixtures and keep both test suites green.

---

## lodgely 0.59.0 specifics

What lodgely's first implementation sends, within the contract above:

| Field | lodgely 0.59.0 |
|---|---|
| `id` | `ld_<lead id>`. |
| `client` key | `Str::slug(client_name)`. Names that differ only in case or punctuation (`Acme`, `acme`) share a key and are served together. Soft-deleted leads count, so a client whose leads were all purged still resolves. |
| `submitted_at` | `created_at` (ingest time) until LG-3. |
| `status_changed_at` | The latest `lead.status_changed` audit event; `null` if the status never changed. |
| `source_type` | `csv`, `email` (both email importers), `manual`, `webhook`, `google_sheets`, `meta_lead_ads` (lodgely's `meta_leads` and the demo `meta_ads`), `openflow`. Other keys pass through if they match `[a-z0-9_]{1,40}`. |
| `attribution.meta_*` | From lodgely's `campaign_id` / `adset_id` / `ad_id` / `form_id` columns (Meta Lead Ads). |
| `attribution.has_gclid` | Always `false` until LG-6 (decision Q-3 b). |
| `attribution.utm_*` | Always `null` until LG-6. |
| `flags` | `duplicate`, `qualified`, `contacted`, `organic`. `spam` is not sent: lodgely has no spam marker. |
| Tombstones | Every soft-deleted lead (manual delete, retention purge, Sheets dedupe) goes out once. A future hard delete (LG-11) must keep emitting one. |
| Errors | `401` / `403` / `404` with `{"message": …}`; `422` for invalid `client` / `limit` / `updated_since`; `400` `Invalid cursor.`; `429` with `Retry-After`. |
| Limits | 60 requests per minute per token; unknown tokens share a 10/min bucket per IP. Each request is logged with token id, client key and row count. |

Tokens are created and revoked under *Settings → API tokens* in lodgely
(scope `lead-metadata:read`), stored as SHA-256 hashes and shown once.

# Security policy

## Supported versions

vigilo is young; only the latest release receives security fixes.

| Version | Supported |
|---|---|
| 0.1.x | ✅ |

## Reporting a vulnerability

Please **do not open a public issue**. Report it privately through GitHub:
[Report a vulnerability](https://github.com/vidual-labs/vigilo/security/advisories/new)
(Security tab → *Advisories*). Include the version, a description, steps to
reproduce and the impact you see. We aim to acknowledge reports within
three working days and to ship a fix for confirmed issues as soon as
possible, crediting you unless you prefer otherwise.

## Security model (what vigilo promises)

- **Read-only towards ad platforms.** vigilo only reads from Google Ads
  (`googleAds:search`) and Meta (GET requests with `ads_read`). A finding
  that vigilo can change a campaign, budget or keyword is a vulnerability.
- **No lead PII.** vigilo stores lead metadata only (see README → *Privacy*).
  Any path by which a lead's name, email address, phone number, free-text
  answer, IP address or click id ends up in vigilo's database is a
  vulnerability.
- **Roles.** Viewers cannot change anything; every mutating action is
  authorised server-side.
- **Secrets.** Per-connection tokens and webhook secrets are encrypted with
  `APP_KEY`; tokens are never shown again after saving.
- **Outbound requests.** vigilo fetches landing pages (GET) and posts
  webhooks to URLs that **operators** enter. Operators are trusted to do
  this; if your network has sensitive internal services, restrict the
  containers' egress accordingly.

## Hardening checklist for self-hosters

- Serve over HTTPS (`VIGILO_SITE_ADDRESS` = your domain), set
  `APP_URL=https://…`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`.
- Keep `APP_DEBUG=false`.
- Change the generated first password (vigilo forces this) and give
  colleagues *viewer* unless they need to change settings.
- Protect `.env` (agency-wide Google/Meta tokens live there) and back up
  `storage/app/.app-key` together with the database.
- Give the Meta system user only *View performance* on ad accounts and the
  token only `ads_read`.
- Keep Docker images updated: `git pull && docker compose up -d --build`.

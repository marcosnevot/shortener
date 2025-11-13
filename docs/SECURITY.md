# Security

## Surface and principles
- **Deny by default**: redirects are only valid if signature/state/limits/expiration are OK.
- **Anti-enumeration**: same 404 codes for invalid cases (slug, signature, ban, expired).
- **Whitelisting**: `SHORTENER_ALLOWED_SCHEMES` and `SHORTENER_DOMAIN_WHITELIST`.
- **API tokens**: stored hash (`token_hash`), `scopes` per action.

## Headers
- `SecurityHeaders` middleware:
  - `Content-Security-Policy` (strict CSP)
  - `Strict-Transport-Security` (HSTS)
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `X-Content-Type-Options: nosniff`
  - Redirects with `Cache-Control: no-store`

## Keys & secrets
- `SHORTENER_HMAC_KEY` (32B). Planned rotation: requires temporary dual validation or regeneration of slugs.
- Secrets in **GitHub Actions** and **Docker** (not in repo).

## Limits
- Rate limits per minute for creation/resolution (env).
- Maximum per link: `max_clicks` field with **atomic** increment.

## Data & Privacy
- No PII in analytics. A minimal event (date, UA, referrer, ip) is sent to the `analytics` queue.
- Aggregation in `clicks_agg` with **k-anonymity** (configurable).

## Testing
- Functional tests cover:
  - HEAD does not consume a click.
  - Atomic `max_clicks` limit.
  - Security headers present in panel.

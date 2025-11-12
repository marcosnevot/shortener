# Environment Configuration (ENV.md)

This document describes **all environment variables** used by the Shortener project, how to generate safe values, and which files to use in each environment (local, CI/testing, and production). Keep this file in sync with code and deployment docs.

> ⚠️ **Never commit real secrets** (`APP_KEY`, database passwords, `SHORTENER_HMAC_KEY`, etc.). Store them in a secret manager and provide them only via `.env` (local), CI secrets (GitHub Actions), or host-level files (production).

---

## 1) Environment files overview

- **Local development:** `.env` at `app/.env` (not committed). Example provided in repo (`.env.example`).
- **Testing (CI & local tests):** `.env.testing` at `app/.env.testing`. Minimal, fast & isolated defaults (e.g., SQLite in-memory, array/async drivers).
- **Production (server):** `shortener.env` copied to the deployment host and referenced by `docker-compose.prod.yml` with `--env-file`.

Directory suggestions:
```
app/.env                # local only (gitignored)
app/.env.testing        # committed (safe defaults, no secrets)
deploy/shortener.env    # on the server (not versioned)
```

---

## 2) Variable reference

### Core app

| Name | Type | Example | Notes |
|---|---|---|---|
| `APP_NAME` | string | `Shortener` | App display name. |
| `APP_ENV` | enum | `local` / `testing` / `production` | Affects error reporting & cache. |
| `APP_KEY` | base64 key | `base64:...` | **Required.** 32 bytes base64; used to encrypt cookies/sessions. |
| `APP_DEBUG` | bool | `true` (local) / `false` (prod) | Disable in production. |
| `APP_URL` | URL | `https://short.example.com` | Used by URL generation and some links. |

Other i18n and maintenance knobs:
| Name | Type | Default | Notes |
|---|---|---|---|
| `APP_LOCALE` | string | `en` | Default locale. |
| `APP_FALLBACK_LOCALE` | string | `en` | Fallback locale. |
| `APP_FAKER_LOCALE` | string | `en_US` | Factories & seeding. |
| `APP_MAINTENANCE_DRIVER` | enum | `file` | Leave `file` unless you have shared storage. |

Security/hardening:
| Name | Type | Example | Notes |
|---|---|---|---|
| `BCRYPT_ROUNDS` | int | `12` | Hash cost for passwords (admin users if added). |
| `LOG_CHANNEL` | enum | `stack` | See Laravel logging. |
| `LOG_LEVEL` | enum | `debug` (local) / `info` (prod) | Reduce noise in prod. |

### Database

| Name | Type | Example | Notes |
|---|---|---|---|
| `DB_CONNECTION` | enum | `mysql` / `sqlite` | MySQL in prod; SQLite in CI tests. |
| `DB_HOST` | hostname | `db` | Service name in Docker Compose. |
| `DB_PORT` | int | `3306` | MySQL port. |
| `DB_DATABASE` | string | `shortener` | Database name. |
| `DB_USERNAME` | string | `shorty` | User with least privileges. |
| `DB_PASSWORD` | string | `secret` | Use a strong password in prod. |

### Cache, Session & Queue

| Name | Type | Example | Notes |
|---|---|---|---|
| `CACHE_STORE` | enum | `redis` (prod), `array` (testing) | Redis recommended in prod. |
| `SESSION_DRIVER` | enum | `database` / `redis` / `array` | `array` for tests, stateful driver in prod. |
| `SESSION_LIFETIME` | minutes | `120` | Session lifetime. |
| `SESSION_ENCRYPT` | bool | `false` | Enable if required by policy. |
| `SESSION_PATH` | path | `/` |  |
| `SESSION_DOMAIN` | domain/null | `null` |  |
| `QUEUE_CONNECTION` | enum | `redis` (prod), `sync` (testing) | Analytics uses a `analytics` queue name. |

Redis:
| Name | Type | Example | Notes |
|---|---|---|---|
| `REDIS_CLIENT` | enum | `phpredis` | Project uses phpredis. |
| `REDIS_HOST` | hostname | `redis` | Service name in Docker Compose. |
| `REDIS_PORT` | int | `6379` |  |
| `REDIS_PASSWORD` | string/null | `null` | Configure if Redis is protected. |

### Mail

| Name | Type | Example | Notes |
|---|---|---|---|
| `MAIL_MAILER` | enum | `log` | `log` in local; SMTP for prod if needed. |
| `MAIL_HOST` | hostname | `127.0.0.1` |  |
| `MAIL_PORT` | int | `2525` |  |
| `MAIL_USERNAME` | string/null | `null` |  |
| `MAIL_PASSWORD` | string/null | `null` |  |
| `MAIL_FROM_ADDRESS` | email | `hello@example.com` |  |
| `MAIL_FROM_NAME` | string | `${APP_NAME}` |  |

### Shortener (domain logic)

| Name | Type | Example | Notes |
|---|---|---|---|
| `SHORTENER_HMAC_KEY` | base64-32 | `CBnsclf0rjpe07z9...` | **Critical.** 32 random bytes, base64. Used to sign slugs. Rotating **invalidates existing slugs**. |
| `SHORTENER_K_ANON` | int | `7` | K-anonymity threshold for public stats. |
| `SHORTENER_MAX_CREATE_PER_MINUTE` | int | `30` | Rate-limit for link creation. |
| `SHORTENER_MAX_RESOLVE_PER_MINUTE` | int | `120` | Rate-limit for resolving/redirects. |
| `SHORTENER_ALLOWED_SCHEMES` | CSV | `https` | Allowed URL schemes for destinations. |
| `SHORTENER_DOMAIN_WHITELIST` | CSV/empty | *(empty)* or `example.com,another.net` | If set, only allows destinations within these registrable domains. |

### Geo / Analytics

| Name | Type | Example | Notes |
|---|---|---|---|
| `GEOIP_DB` | path | `/var/www/html/storage/app/geoip/GeoLite2-Country.mmdb` | Optional; for country-level analytics. Ensure file is present inside the container or mount it. |

---

## 3) Generating **secure** values

### `APP_KEY` (base64, 32 bytes)
- **Laravel** (inside container or local PHP):  
  ```bash
  php artisan key:generate --show
  ```
- **OpenSSL (Linux/macOS):**  
  ```bash
  openssl rand -base64 32
  ```
- **PowerShell (Windows):**  
  ```powershell
  [Convert]::ToBase64String((New-Object byte[] 32 | %{[void](New-Object System.Security.Cryptography.RNGCryptoServiceProvider).GetBytes($_);$_}))
  ```

### `SHORTENER_HMAC_KEY` (base64, 32 bytes)
- **Linux/macOS:**  
  ```bash
  openssl rand -base64 32
  ```
- **PHP one-liner:**  
  ```bash
  php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
  ```

> Keep both keys secret. Rotating `SHORTENER_HMAC_KEY` **breaks old slugs** by design. Plan rotation windows accordingly.

---

## 4) Recommended files by environment

### A) Local development — `app/.env`
Example (adjust DB/Redis if your compose names differ):
```dotenv
APP_NAME=Shortener
APP_ENV=local
APP_KEY=base64:REPLACE_ME
APP_DEBUG=true
APP_URL=http://localhost:8080

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12
LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=shortener
DB_USERNAME=shorty
DB_PASSWORD=secret

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

CACHE_STORE=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

SHORTENER_HMAC_KEY=base64:REPLACE_ME
SHORTENER_K_ANON=7
SHORTENER_MAX_CREATE_PER_MINUTE=30
SHORTENER_MAX_RESOLVE_PER_MINUTE=120
SHORTENER_ALLOWED_SCHEMES=https
SHORTENER_DOMAIN_WHITELIST=

MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"

GEOIP_DB=/var/www/html/storage/app/geoip/GeoLite2-Country.mmdb
```

### B) Testing (CI & local) — `app/.env.testing`
Lean settings to make tests fast and deterministic:
```dotenv
APP_NAME=Shortener
APP_ENV=testing
APP_KEY=base64:testingtestingtestingtestingtestingtest=
APP_DEBUG=true
APP_URL=http://localhost

# Avoid external services in tests
DB_CONNECTION=sqlite
DB_DATABASE=:memory:

CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync

REDIS_CLIENT=phpredis
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=null

SHORTENER_HMAC_KEY=base64:testingtestingtestingtestingtestingtest=
SHORTENER_K_ANON=7
SHORTENER_MAX_CREATE_PER_MINUTE=1000
SHORTENER_MAX_RESOLVE_PER_MINUTE=5000
SHORTENER_ALLOWED_SCHEMES=https
SHORTENER_DOMAIN_WHITELIST=

MAIL_MAILER=array
MAIL_FROM_ADDRESS="test@example.com"
MAIL_FROM_NAME="${APP_NAME}"

GEOIP_DB=/tmp/GeoLite2-Country.mmdb
```

> The test suite itself ensures view/cache directories exist, so no extra FS steps are required in CI. If you run tests manually, ensure `storage/framework/{cache,views,sessions}` exist (Laravel will create them when needed).

### C) Production — `deploy/shortener.env`
This file **lives on the server** and is referenced by your `docker-compose.prod.yml` with `--env-file`.

```dotenv
APP_NAME=Shortener
APP_ENV=production
APP_KEY=base64:REPLACE_ME
APP_DEBUG=false
APP_URL=https://short.example.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12
LOG_CHANNEL=stack
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=shortener
DB_USERNAME=shorty
DB_PASSWORD=CHANGE_ME

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

CACHE_STORE=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

SHORTENER_HMAC_KEY=base64:REPLACE_ME
SHORTENER_K_ANON=7
SHORTENER_MAX_CREATE_PER_MINUTE=30
SHORTENER_MAX_RESOLVE_PER_MINUTE=120
SHORTENER_ALLOWED_SCHEMES=https
SHORTENER_DOMAIN_WHITELIST=

MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"

# Optional — mount or bake the file into the image if you need country analytics
GEOIP_DB=/var/www/html/storage/app/geoip/GeoLite2-Country.mmdb
```

---

## 5) Secrets in CI/CD

- **GitHub Actions (CI):** create repository **Secrets** (not variables) for anything sensitive. At minimum:
  - `DOCKERHUB_USERNAME`
  - `DOCKERHUB_TOKEN` (or PAT with `write:packages` on Docker Hub)
- Use `${{ secrets.NAME }}` in workflow YAML (already set in `ci.yml`).

---

## 6) Troubleshooting

- **`Please provide a valid cache path` in CI/tests:** ensure tests create/point `storage/framework/views` and `storage/framework/cache`. The current test suite already handles it; if running tests manually, clear/prepare with:
  ```bash
  php artisan optimize:clear
  mkdir -p storage/framework/{cache,views,sessions}
  ```
- **HMAC signature mismatch:** `SHORTENER_HMAC_KEY` differs from the one that created existing slugs. Rotations intentionally invalidate old slugs.
- **Redis/Queue unavailable:** fall back to `QUEUE_CONNECTION=sync` temporarily (not recommended for prod) or restore Redis.

---

## 7) Change log (ENV-impacting)

- v1: Initial set covering app/db/cache/redis/mail + shortener-specific knobs and GeoIP path.

---

## 8) Appendix — Policy hints

- Use `https` only in `SHORTENER_ALLOWED_SCHEMES` for production.
- If you must allow `http`, do it per-environment and behind internal networks only.
- Define `SHORTENER_DOMAIN_WHITELIST` for tenant- or org-scoped deployments (comma-separated, no spaces).


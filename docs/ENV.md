# Environment Configuration (ENV.md)

This document lists **all environment variables** used by Shortener and how to set them **for local development and testing (CI)** in *portfolio mode* (no production deploy).  
If later you decide to deploy publicly, you can extend this file with a production section.

> ⚠️ **No real secrets in Git.** Never commit values for `APP_KEY`, database passwords or `SHORTENER_HMAC_KEY`. Keep them locally in `.env` or as CI **Secrets**.

---

## 1) Environment files overview

- **Local development:** `app/.env` (gitignored). See the example below.
- **Testing (CI & local tests):** `app/.env.testing` (committed) with fast, isolated defaults (SQLite in‑memory, array cache, sync queue).

Folder layout (relevant parts):

```
app/
├─ .env               # local only (not committed)
└─ .env.testing       # committed: safe defaults for tests
```

---

## 2) Variable reference

### Core app

| Name | Type | Example | Notes |
|---|---|---|---|
| `APP_NAME` | string | `Shortener` | Display name. |
| `APP_ENV` | enum | `local` / `testing` | Affects error reporting & caches. |
| `APP_KEY` | base64 key | `base64:...` | **Required.** 32 bytes base64 for encryption. |
| `APP_DEBUG` | bool | `true` (local) / `false` (tests) | Disable in production (if ever added). |
| `APP_URL` | URL | `http://localhost:8080` | Used by URL generation. |

Other knobs:

| Name | Type | Default | Notes |
|---|---|---|---|
| `APP_LOCALE` | string | `en` | Default locale. |
| `APP_FALLBACK_LOCALE` | string | `en` | Fallback. |
| `APP_FAKER_LOCALE` | string | `en_US` | Factories & seeding. |
| `APP_MAINTENANCE_DRIVER` | enum | `file` | Keep `file`. |

Logging / security:

| Name | Type | Example | Notes |
|---|---|---|---|
| `BCRYPT_ROUNDS` | int | `12` | Hash cost (if users are added in the future). |
| `LOG_CHANNEL` | enum | `stack` | See Laravel logging. |
| `LOG_LEVEL` | enum | `debug` (local) | Reduce noise in CI if needed. |

### Database

| Name | Type | Example | Notes |
|---|---|---|---|
| `DB_CONNECTION` | enum | `mysql` / `sqlite` | MySQL in local Docker; **SQLite in tests**. |
| `DB_HOST` | hostname | `db` | Service name in Docker Compose (local). |
| `DB_PORT` | int | `3306` | MySQL port. |
| `DB_DATABASE` | string | `shortener` | Database name. |
| `DB_USERNAME` | string | `shorty` | Least-privileged user. |
| `DB_PASSWORD` | string | `secret` | Strong password locally is fine. |

### Cache, Session & Queue

| Name | Type | Example | Notes |
|---|---|---|---|
| `CACHE_STORE` | enum | `redis` (local), `array` (testing) | Use `array` for speed in tests. |
| `SESSION_DRIVER` | enum | `database` / `redis` / `array` | `array` in tests, stateful in local. |
| `SESSION_LIFETIME` | minutes | `120` | Session TTL. |
| `SESSION_ENCRYPT` | bool | `false` | Enable if policy requires. |
| `SESSION_PATH` | path | `/` |  |
| `SESSION_DOMAIN` | domain/null | `null` |  |
| `QUEUE_CONNECTION` | enum | `redis` (local), `sync` (testing) | Analytics worker not needed in tests. |

**Redis** (used in local dev for cache/queues/metrics):

| Name | Type | Example | Notes |
|---|---|---|---|
| `REDIS_CLIENT` | enum | `phpredis` | This project uses phpredis. |
| `REDIS_HOST` | hostname | `redis` | Service name in Docker Compose (local). |
| `REDIS_PORT` | int | `6379` |  |
| `REDIS_PASSWORD` | string/null | `null` | Set if your local Redis requires auth. |

### Mail

| Name | Type | Example | Notes |
|---|---|---|---|
| `MAIL_MAILER` | enum | `log` | `log` locally; no SMTP needed. |
| `MAIL_HOST` | hostname | `127.0.0.1` |  |
| `MAIL_PORT` | int | `2525` |  |
| `MAIL_USERNAME` | string/null | `null` |  |
| `MAIL_PASSWORD` | string/null | `null` |  |
| `MAIL_FROM_ADDRESS` | email | `hello@example.com` |  |
| `MAIL_FROM_NAME` | string | `${APP_NAME}` |  |

### Shortener (domain logic)

| Name | Type | Example | Notes |
|---|---|---|---|
| `SHORTENER_HMAC_KEY` | base64-32 | `base64:...` | **Critical.** 32 random bytes, base64. Used to sign slugs. Rotating invalidates old slugs (by design). |
| `SHORTENER_K_ANON` | int | `7` | k‑anonymity threshold for public stats. |
| `SHORTENER_MAX_CREATE_PER_MINUTE` | int | `30` | Rate-limit for link creation. |
| `SHORTENER_MAX_RESOLVE_PER_MINUTE` | int | `120` | Rate-limit for resolving/redirects. |
| `SHORTENER_ALLOWED_SCHEMES` | CSV | `https` | Allowed destination URL schemes. |
| `SHORTENER_DOMAIN_WHITELIST` | CSV/empty | *(empty)* or `example.com,another.net` | If set, restricts to those registrable domains. |

### Geo / Analytics (optional)

| Name | Type | Example | Notes |
|---|---|---|---|
| `GEOIP_DB` | path | `/var/www/html/storage/app/geoip/GeoLite2-Country.mmdb` | Optional; for country‑level analytics. Place file inside the container or mount it. |

---

## 3) Generating **secure** values

### `APP_KEY` (base64, 32 bytes)
- **Laravel (inside the app container or local PHP):**
  ```bash
  php artisan key:generate --show
  ```
- **OpenSSL (Linux/macOS):**
  ```bash
  openssl rand -base64 32
  ```
- **PowerShell (Windows):**
  ```powershell
  [Convert]::ToBase64String((1..32 | % { Get-Random -Maximum 256 } | ForEach-Object {[byte]$_}))
  ```

### `SHORTENER_HMAC_KEY` (base64, 32 bytes)
- **Linux/macOS:**
  ```bash
  openssl rand -base64 32
  ```
- **PHP one‑liner:**
  ```bash
  php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
  ```

> Keep both keys secret. Rotating `SHORTENER_HMAC_KEY` **breaks old slugs**. Do it only between demos if necessary.

---

## 4) Recommended files by environment

### A) Local development — `app/.env`
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
*(fast & deterministic)*
```dotenv
APP_NAME=Shortener
APP_ENV=testing
APP_KEY=base64:testingtestingtestingtestingtestingtest=
APP_DEBUG=false
APP_URL=http://localhost

# In-memory DB for speed
DB_CONNECTION=sqlite
DB_DATABASE=:memory:

# No external services in tests
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
VIEW_COMPILED_PATH=/tmp

# HMAC for tests (doesn't need to be secret)
SHORTENER_HMAC_KEY=base64:testingtestingtestingtestingtestingtest=
SHORTENER_K_ANON=7
SHORTENER_MAX_CREATE_PER_MINUTE=1000
SHORTENER_MAX_RESOLVE_PER_MINUTE=5000
SHORTENER_ALLOWED_SCHEMES=https
SHORTENER_DOMAIN_WHITELIST=

MAIL_MAILER=array
MAIL_FROM_ADDRESS="test@example.com"
MAIL_FROM_NAME="${APP_NAME}"

# Optional: path used by tests if GeoIP is needed
GEOIP_DB=/tmp/GeoLite2-Country.mmdb
```

> The test suite and CI workflow ensure cache/view paths exist. If you run tests manually outside Docker, prepare them with:
> ```bash
> php artisan optimize:clear
> mkdir -p storage/framework/{cache,views,sessions}
> ```

---

## 5) CI secrets (optional)

If your CI workflow is configured to **push a Docker image** (optional), set repository **Secrets** (not Variables) in GitHub:
- `DOCKERHUB_USERNAME`
- `DOCKERHUB_TOKEN`

If you don’t set them, the CI will still run tests; the push step can be skipped/fail without affecting your local use.

---

## 6) Troubleshooting

- **`Please provide a valid cache path` in tests:** ensure `VIEW_COMPILED_PATH` is set (e.g. `/tmp`) and the `storage/framework/...` dirs exist. See tip above.
- **HMAC signature mismatch:** your `SHORTENER_HMAC_KEY` changed; previously generated slugs won’t validate.
- **Redis not running locally:** switch to `CACHE_STORE=array` and `QUEUE_CONNECTION=sync` temporarily for demos (less realistic, but works).

---

## 7) Change log (ENV-impacting)

- **v1 (portfolio mode):** Initial local + testing coverage; production section removed while `deploy/` is absent.

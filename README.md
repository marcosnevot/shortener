**English** | [Español](README.es.md)

# Shortener — URL shortener with HMAC signatures and *privacy‑first* analytics

![CI](https://github.com/marcosnevot/shortener/actions/workflows/ci.yml/badge.svg)

> Laravel 12 + MySQL 8 + Redis 7. HMAC-signed slugs, minimal panel, Prometheus metrics, *rate limits*, and statistics with *k‑anonymity*.
>
> **Portfolio mode**: does not include production deployment. Runs locally with Docker and is validated with CI (tests).

---

## Table of contents
- [Features](#features)
- [Screenshots](#screenshots)
- [Architecture](#architecture)
- [Security](#security)
- [Requirements](#requirements)
- [Environment variables](#environment-variables)
  - [.env (development)](#env-development)
  - [.env.testing (CI)](#envtesting-ci)
- [Local run (Docker)](#local-run-docker)
- [Tests](#tests)
- [API](#api)
- [Observability](#observability)
- [Rate limits](#rate-limits)
- [Queues and analytics](#queues-and-analytics)
- [Project structure](#project-structure)
- [CI (GitHub Actions)](#ci-github-actions)
- [Roadmap](#roadmap)
- [License](#license)

---

## Features
- ✅ **HMAC-signed slugs**: prevents slug tampering; verification on each resolution.
- ✅ **HEAD-safe**: `HEAD` requests **do not** consume a click.
- ✅ **Usage limits**: for creation and resolution (`SHORTENER_MAX_*`).
- ✅ **Whitelist** of allowed schemes and domains.
- ✅ **Privacy-first statistics**: aggregated with *k-anonymity* (buckets < *k* are not exposed).
- ✅ **Prometheus metrics** (latencies and counters) stored in Redis.
- ✅ **Minimal panel** with strict security headers.
- ✅ **Tests** (PHPUnit) covering slug/HMAC, redirect, headers, and basic API.
- ✅ **Multi-stage Docker** (no Composer in *runtime*).
- ✅ **CI**: GitHub Actions (tests; optional image build & push if secrets exist).

---

## Screenshots

**Panel**
![Panel](docs/images/panel.png)

**Create link**
![Create link](docs/images/create-link.png)

**Redirection (302)**
<p align="center">
  <img src="docs/images/demo.gif" alt="Redirection 302 Demo: click on slug and load target" width="900">
</p>

**Link Details**
![Details](docs/images/details-link.png)

**/metrics (Prometheus)**
![Metrics](docs/images/metrics.png)

**/health**
![Health](docs/images/health.png)

---

## Architecture
- **App**: Laravel 12 (PHP 8.3 FPM).
- **DB**: MySQL 8 (link and token persistence).
- **Cache/Queues/Metrics**: Redis 7.
- **Jobs**: `IngestClickEvent` (classifies and aggregates analytics).
- **Metrics**: `App\Services\MetricsContract` → `Metrics` (Redis).
- **SlugService**: `base62(id)` + `_` + `sig(11)`; `sig = HMAC(id|url)`.
- **Endpoints**:
  - Web: `/r/{slug}` (GET/HEAD), `/`
  - API: create/read/ban/delete; aggregated statistics.

> See more details in **OBSERVABILITY.md**.

---

## Security
- **HMAC**: key `SHORTENER_HMAC_KEY` (32 bytes base64) to sign/verify slugs.
- **Deny-by-default policy** for URLs: allowed schemes (`SHORTENER_ALLOWED_SCHEMES`) and domain *whitelist* (`SHORTENER_DOMAIN_WHITELIST`).
- **Headers**: CSP, HSTS, Referrer-Policy, etc. via `SecurityHeaders` middleware.
- **Anti-enumeration**: indistinguishable 404 for cases `bad_slug`, `bad_sig`, `banned`, `expired`, `limit_reached`, `not_found`.
- **API Tokens**: `api_tokens` table (SHA-256 hash + `scopes`).

---

## Requirements
- Docker 24+ and Docker Compose v2.
- (Optional) PHP 8.3 + Composer 2 if you prefer to run without Docker.
- Docker Hub account **not required** in portfolio mode (local only).

---

## Environment variables

### `.env` (development)
Minimal example (adjust values if yours differ):

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080
APP_KEY=base64:...             # generate with: php artisan key:generate --show

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=shortener
DB_USERNAME=shorty
DB_PASSWORD=secret

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database

SHORTENER_HMAC_KEY=base64:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX=
SHORTENER_K_ANON=7
SHORTENER_MAX_CREATE_PER_MINUTE=30
SHORTENER_MAX_RESOLVE_PER_MINUTE=120
SHORTENER_ALLOWED_SCHEMES=https
SHORTENER_DOMAIN_WHITELIST=

GEOIP_DB=/var/www/html/storage/app/geoip/GeoLite2-Country.mmdb
```

> **Note**: `SHORTENER_HMAC_KEY` must be a secure 32-byte base64 key.

### `.env.testing` (CI)
Used by PHPUnit in GitHub Actions (avoids *view cache* issues and ensures `APP_KEY`):

```env
APP_ENV=testing
APP_KEY=base64:testingappkeymustexist++++++++++++++==
APP_DEBUG=false
APP_URL=http://localhost

DB_CONNECTION=sqlite
DB_DATABASE=:memory:

CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
VIEW_COMPILED_PATH=/tmp
FILESYSTEM_DISK=local

SHORTENER_HMAC_KEY=base64:testingkeyforhmac++++++++++++/w==
SHORTENER_K_ANON=7
SHORTENER_MAX_CREATE_PER_MINUTE=9999
SHORTENER_MAX_RESOLVE_PER_MINUTEExpired=9999
SHORTENER_ALLOWED_SCHEMES=https
SHORTENER_DOMAIN_WHITELIST=
```

---

## Local run (Docker)

From the repo root:

```bash
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose exec app php artisan storage:link
```

- App: <http://localhost:8080>  
- Panel: `/`  
- Metrics: `/metrics` (Prometheus text)  
- Health: `/health`

**Restart stack / logs**

```bash
docker compose restart
docker compose logs -f --tail=200
```

**Optional: analytics worker (foreground)**

```bash
docker compose exec app php artisan queue:work --queue=analytics --sleep=1
```

---

## Tests

```bash
docker compose exec app ./vendor/bin/phpunit -d memory_limit=512M
```

> In CI a `.env.testing` and temporary view compilation paths are prepared automatically.

---

## API

**Authentication**: header `X-Api-Token: <token>` (hash verified in backend).

### Create link
`POST /api/links`
```json
{
  "url": "https://example.com/page",
  "max_clicks": 100,
  "expires_at": "2026-01-01T00:00:00Z"
}
```
**201**:
```json
{
  "id": 123,
  "slug": "1ZAbcDeF_9dQwE",
  "url": "https://example.com/page"
}
```

### Show link
`GET /api/links/{id}` → **200** with link data | **404** if it does not exist.

### Delete link
`DELETE /api/links/{id}` → **204** | **404**.

### Ban link
`POST /api/links/{id}/ban` → **204** | **404**.

### Stats (aggregated, *privacy-first*)
`GET /api/stats/{slug}`  
Returns aggregates that meet `k ≥ SHORTENER_K_ANON`.

> Complete specification in `docs/API/openapi.yaml` (OpenAPI 3.0).

---

## Observability

- **/metrics** (Prometheus):
  - `redirect_requests_total{result=...}`
  - `redirect_duration_seconds_bucket/sum/count`
- **/health**: simple *liveness/readiness* (optionally DB/Redis ping).
- **Logs**: `stack` channel and configurable level (`LOG_LEVEL`).

> More details in **OBSERVABILITY.md**.

---

## Rate limits
- **Creation**: `SHORTENER_MAX_CREATE_PER_MINUTE` per token/IP.
- **Resolution**: `SHORTENER_MAX_RESOLVE_PER_MINUTE` per IP.
- Implemented with Laravel *named limiters*; **429** responses with `Retry-After`.

---

## Queues and analytics
- Clicks (only on `GET`) enqueue `IngestClickEvent` → `analytics` queue.
- Locally, you can launch `queue:work` manually (see run section).

---

## Project structure

```
.
├─ app/                      # Laravel code + image Dockerfile
│  └─ Dockerfile
├─ docs/
│  ├─ API/
│  │  └─ openapi.yaml        # OpenAPI 3.0 specification
│  ├─ ENV.md
│  ├─ OBSERVABILITY.md
│  ├─ OPERATIONS.md
│  └─ SECURITY.md
├─ .github/
│  └─ workflows/
│     └─ ci.yml              # CI: tests + (push image if secrets exist)
├─ docker-compose.yml        # Local stack (app, db, redis…)
├─ .gitignore
├─ LICENSE
├─ README.md
├─ CONTRIBUTING.md
└─ CHANGELOG.md
```

> The `deploy/` folder and **DEPLOYMENT.md** have been removed in portfolio mode.

---

## CI (GitHub Actions)

- **tests**: installs dependencies and runs PHPUnit with `.env.testing`.
- **build & push (optional)**: if you configure Docker Hub secrets (`DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN`), it builds the image with `./app/Dockerfile` and publishes it.

You can disable the *push* part by removing the build job or leaving the secrets undefined.

---

## Roadmap
- [ ] Stats panel (aggregated, *k-anon*).
- [ ] Publish OpenAPI as static documentation.
- [ ] Integration with Pushgateway (optional) for *batch jobs*.
- [ ] Canary for analytics worker.

---

## License
MIT

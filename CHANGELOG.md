# Changelog
All notable changes to this project will be documented in this file.

The format is based on **Keep a Changelog** and this project adheres to **Semantic Versioning**.

## [Unreleased]

### Added
- (Docs) ENV.md, OBSERVABILITY.md, OPERATIONS.md, CONTRIBUTING.md.
- (CI) GitHub Actions workflow: runs PHPUnit and builds & pushes the Docker image using Buildx + GHA cache.


### Changed
- (Security) Hardening of HTTP headers in `SecurityHeaders` middleware.
- (Build) Optimized multi-stage Dockerfile in `app/`: vendor in `composer` stage, runtime `php:8.3-fpm-alpine`, opcache.
- (Metrics) `MetricsContract` interface and `Metrics` class with counters and histograms (Redis) + `/metrics` endpoint (Prometheus).

### Fixed
- (CI) Fixed “Please provide a valid cache path” error by creating `bootstrap/cache` and pointing `VIEW_COMPILED_PATH` in tests.
- (Slug) HMAC signature injected from `config('shortener.hmac_key')` to avoid `null` in CI.
- (Docker) Permission error when copying `storage/` and `bootstrap/cache` during build.

---

## [0.1.0] - 2025-11-12
First public release (MVP) of the **URL Shortener with HMAC signatures and privacy-first analytics**.

### Added
- URL shortening with signed (HMAC) base62 slugs: create, read, ban, delete.
- Minimalist web panel (Blade) for management.
- Resolution logic with:
  - Signature verification,
  - Expiration control,
  - Atomic click limit,
  - HEAD-safe (does not consume a click).
- Privacy-first analytics:
  - `IngestClickEvent` job on `analytics` queue,
  - Aggregation into `clicks_agg` table (k-anon).
- Security:
  - Scheme whitelist (`SHORTENER_ALLOWED_SCHEMES`),
  - Rate limits for creation and resolution,
  - Security headers.
- Observability:
  - `/health` and `/metrics` (Prometheus),
  - Metrics: `redirect_requests_total`, `redirect_duration_seconds`, API metrics.
- Infra:
  - Dockerfile (multi-stage),
  - Docker Compose (local),
  - CI/CD pipeline (GitHub Actions) with build & push to registry.

### Notes
- Requires MySQL 8, Redis 7 and PHP 8.3.
- Enable variables/secrets for image push in the pipeline.

[0.1.0]: https://github.com/marcosnevot/shortener/releases/tag/v0.1.0

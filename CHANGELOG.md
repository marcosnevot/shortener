# Changelog
All notable changes to this project will be documented in this file.

The format is based on **Keep a Changelog** and this project adheres to **Semantic Versioning**.

## [Unreleased]

### Added
- (Docs) DEPLOYMENT.md, ENV.md, OBSERVABILITY.md, CONTRIBUTING.md.
- (CI) GitHub Actions workflow: ejecuta PHPUnit y build & push de la imagen Docker usando Buildx + caché GHA.
- (Release) Plantilla `docker-compose.prod.yml` y `shortener.env` para despliegue en servidor.

### Changed
- (Security) Endurecimiento de cabeceras HTTP en middleware `SecurityHeaders`.
- (Build) Dockerfile multi-stage en `app/` optimizado: vendor en stage `composer`, runtime `php:8.3-fpm-alpine`, opcache.
- (Metrics) Contrato `MetricsContract` y clase `Metrics` con contador y histogramas (Redis) + endpoint `/metrics` (Prometheus).

### Fixed
- (CI) Fijado error de *“Please provide a valid cache path”* creando `bootstrap/cache` y apuntando `VIEW_COMPILED_PATH` en tests.
- (Slug) Firma HMAC inyectada desde `config('shortener.hmac_key')` para evitar `null` en CI.
- (Docker) Error de permisos copiando `storage/` y `bootstrap/cache` durante build.

---

## [0.1.0] - 2025-11-12
Primera versión pública (MVP) del **URL Shortener con firmas HMAC y analítica privacy-first**.

### Added
- Acortado de URLs con *slugs* firmados (HMAC) y base62: creación, lectura, *ban*, borrado.
- Panel web (Blade) minimalista para gestión.
- Lógica de resolución con:
  - Verificación de firma,
  - Control de expiración,
  - Límite de clics atómico,
  - HEAD-safe (no consume click).
- Analítica privacy-first:
  - Job `IngestClickEvent` en cola `analytics`,
  - Agregación a tabla `clicks_agg` (k-anon).
- Seguridad:
  - Lista blanca de esquemas (`SHORTENER_ALLOWED_SCHEMES`),
  - Rate limits de creación y resolución,
  - Cabeceras de seguridad.
- Observabilidad:
  - `/health` y `/metrics` (Prometheus),
  - Métricas: `redirect_requests_total`, `redirect_duration_seconds`, métricas de API.
- Infra:
  - Dockerfile (multi-stage),
  - Docker Compose (local),
  - Pipeline de CI/CD (GitHub Actions) con build & push a registro.

### Notes
- Requiere MySQL 8, Redis 7 y PHP 8.3.
- Habilitar variables/secrets para el *push* de imágenes en el pipeline.

[0.1.0]: https://github.com/marcosnevot/shortener/releases/tag/v0.1.0

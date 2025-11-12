# Architecture

## Goals
- URL shortener con **firmas HMAC** antienumeración.
- Analítica **privacy-first** (k-anonymity), sin PII.
- **Performance**: caché y contadores atómicos; colas para ingestión.
- **Observabilidad** con métricas exportables a Prometheus.
- **CI/CD** con GitHub Actions  Docker Hub  despliegue por Compose.

## High-Level
- **App**: Laravel 12 (PHP 8.3), controladores:
  - Web: RedirectController (GET/HEAD /{slug}) con validación HMAC, límites y métricas.
  - Panel: PanelController (vista Blade).
  - API: creación/consulta/baneo/borrado de links + stats.
- **DB**: MySQL 8
  - links: id (PK), url, is_banned, max_clicks, clicks_count, expires_at, deleted_at, timestamps.
  - clicks_agg: agregados por ventana (se actualiza en background).
- **Cache/Queue/Metrics**: Redis 7
  - Cache de Link por id.
  - Cola analytics para IngestClickEvent.
  - Métricas en claves Redis (counter/histogram).
- **Slug & firma**
  - id  **base62**  idB62.
  - sig = truncamiento **base64url(HMAC-SHA256(id|url, key)) a 11 chars.
  - slug = idB62 + "_" + sig.
  - Validación en RedirectController: si firma, estado, expiración o límites fallan  **404** (anti-enumeración).
- **Límites**
  - Env: SHORTENER_MAX_CREATE_PER_MINUTE, SHORTENER_MAX_RESOLVE_PER_MINUTE.
  - max_clicks atómico vía whereColumn('<','max_clicks')->increment('clicks_count').
- **Seguridad**
  - SecurityHeaders middleware: CSP estricta, HSTS, Referrer-Policy, no-store en redirecciones.
  - Lista blanca de dominios y esquemas (SHORTENER_DOMAIN_WHITELIST, SHORTENER_ALLOWED_SCHEMES).
  - Token API por tabla api_tokens (	oken_hash, scopes).
- **Observabilidad**
  - MetricsContract / Metrics (Redis):
    - 
edirect_requests_total{result}
    - 
edirect_duration_seconds (histograma)
  - Endpoint /metrics (Prometheus-friendly) y /health.
- **CI/CD**
  - Workflow ci.yml: test PHPUnit, buildx, push a Docker Hub.
  - Imagen runtime: php:8.3-fpm-alpine + vendor desde stage Composer.
  - docker-compose.prod.yml levanta app + db + redis y se alimenta de deploy/shortener.env.

## Estructura relevante
- app/Http/Controllers/Web/RedirectController.php
- app/Jobs/IngestClickEvent.php (ingesta  clicks_agg)
- app/Services/SlugService.php, app/Services/MetricsContract.php, app/Services/Metrics.php
- 	ests/Feature/*, 	ests/Unit/*
- .github/workflows/ci.yml
- deploy/docker-compose.prod.yml, deploy/shortener.env (plantilla)
- app/Dockerfile (imagen final)

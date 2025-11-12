# Shortener — URL shortener con firmas HMAC y analítica *privacy-first*

> Laravel 12 + MySQL 8 + Redis 7. Slugs firmados (HMAC), panel mínimo, métricas Prometheus, *rate limits* y estadísticas con *k-anonymity*.

---

## Tabla de contenido
- [Características](#características)
- [Arquitectura](#arquitectura)
- [Seguridad](#seguridad)
- [Requisitos](#requisitos)
- [Configuración](#configuración)
  - [.env (desarrollo)](#env-desarrollo)
  - [.env.testing (CI)](#envtesting-ci)
  - [`deploy/shortener.env` (producción)](#deployshortenerenv-producción)
- [Ejecución local (Docker)](#ejecución-local-docker)
- [Base de datos](#base-de-datos)
- [API](#api)
- [Métricas y salud](#métricas-y-salud)
- [Rate limits](#rate-limits)
- [Colas y analítica](#colas-y-analítica)
- [CI/CD](#cicd)
- [Despliegue en producción](#despliegue-en-producción)
- [Operación (runbooks)](#operación-runbooks)
- [Roadmap](#roadmap)
- [Licencia](#licencia)

---

## Características
- ✅ **Slugs firmados (HMAC)**: evita manipulación del slug; verificación en cada resolución.
- ✅ **HEAD-safe**: las peticiones `HEAD` no consumen clic.
- ✅ **Límites de uso**: por creación y resolución (`SHORTENER_MAX_*`).
- ✅ **Lista blanca de esquemas/dominios**.
- ✅ **Estadísticas *privacy-first***: agregadas con *k-anonymity* (no se exponen buckets < *k*).
- ✅ **Métricas Prometheus** (latencia y contadores) almacenadas en Redis.
- ✅ **Panel mínimo** con cabeceras de seguridad estrictas.
- ✅ **Pruebas** (PHPUnit) cubriendo slug/HMAC, redirect, headers, API básica.
- ✅ **Docker** multi-stage (sin Composer en *runtime*).
- ✅ **CI**: GitHub Actions (tests + buildx + push a Docker Hub).
- ✅ **CD**: `docker compose` con `.env` de producción y *healthchecks*.

---

## Arquitectura
- **App**: Laravel 12 (PHP 8.3 FPM).
- **DB**: MySQL 8 (persistencia de enlaces y tokens).
- **Cache/Colas/Metrics**: Redis 7.
- **Jobs**: `IngestClickEvent` (clasifica y agrega analítica).
- **Métricas**: `App\Services\MetricsContract` → `Metrics` (Redis).
- **SlugService**: `base62(id)` + `_` + `sig(11)`; `sig = HMAC(id|url)`.
- **Endpoints**:
  - Web: `/{slug}` (GET/HEAD), `/panel`
  - API: creación/consulta/baneo/borrado; estadísticas agregadas.

---

## Seguridad
- **HMAC**: clave `SHORTENER_HMAC_KEY` (32 bytes base64) para firmar/verificar slugs.
- **Política *deny-by-default*** en validación de URLs: esquemas permitidos (`SHORTENER_ALLOWED_SCHEMES`) y *whitelist* de dominio (`SHORTENER_DOMAIN_WHITELIST`).
- **Cabeceras**: CSP, HSTS, Referrer-Policy, etc. vía `SecurityHeaders` middleware.
- **Anti-enumeración**: 404 indistinguible en casos `bad_slug`, `bad_sig`, `banned`, `expired`, `limit_reached`, `not_found`.
- **Auditoría sin datos sensibles** (los jobs no logean IP completas).
- **Tokens API**: tabla `api_tokens` (hash SHA-256 + `scopes`).

---

## Requisitos
- Docker 24+ y Docker Compose v2.
- Docker Hub (o registro compatible) si vas a publicar imágenes.
- (Alternativa) PHP 8.3 + Composer 2 para ejecución sin Docker.

---

## Configuración

### `.env` (desarrollo)
Ejemplo (resumen; ya lo tienes configurado):

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080
APP_KEY=base64:...        # php artisan key:generate

DB_HOST=db
DB_DATABASE=shortener
DB_USERNAME=shorty
DB_PASSWORD=secret

REDIS_CLIENT=phpredis
REDIS_HOST=redis

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

### `.env.testing` (CI)
Para PHPUnit en GitHub Actions (evita problemas de *view cache* y *APP_KEY*):

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
SHORTENER_MAX_RESOLVE_PER_MINUTE=9999
SHORTENER_ALLOWED_SCHEMES=https
SHORTENER_DOMAIN_WHITELIST=
```

> En el pipeline ya se crean carpetas temporales y *bootstrap/cache* si hace falta.

### `deploy/shortener.env` (producción)
Claves reales y *debug* desactivado:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tudominio.example

APP_KEY=base64:...                 # genera una vez y guarda
LOG_LEVEL=info

DB_HOST=db
DB_PORT=3306
DB_DATABASE=shortener
DB_USERNAME=shorty
DB_PASSWORD=***seguro***

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database

SHORTENER_HMAC_KEY=base64:...      # 32 bytes base64, rotación controlada
SHORTENER_K_ANON=7
SHORTENER_MAX_CREATE_PER_MINUTE=30
SHORTENER_MAX_RESOLVE_PER_MINUTE=120
SHORTENER_ALLOWED_SCHEMES=https
SHORTENER_DOMAIN_WHITELIST=        # ej: example.com,mi-dominio.es

GEOIP_DB=/var/www/html/storage/app/geoip/GeoLite2-Country.mmdb
```

---

## Ejecución local (Docker)

```bash
# desde la raíz del repo
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose exec app php artisan storage:link
```

- App: http://localhost:8080  
- Panel: `/panel`  
- Métricas: `/metrics` (texto Prometheus)  
- Salud: `/health`

---

## Base de datos
**Tablas clave** (resumen):
- `links`: `id`, `url`, `clicks_count`, `max_clicks`, `expires_at`, `is_banned`, `deleted_at`, timestamps.
- `api_tokens`: `id`, `name`, `token_hash` (SHA-256), `scopes` (JSON), timestamps.
- `clicks_agg`: agregados por ventana (país, *ua class*, etc.), sin PII. *Upsert* idempotente.

> Ejecuta migraciones con `php artisan migrate`.

**Crear un token API** (ejemplo rápido):

```sql
INSERT INTO api_tokens (name, token_hash, scopes, created_at, updated_at)
VALUES (
  'ci',
  SHA2('mi_token_plano', 256),
  JSON_ARRAY('links:create','links:read','links:stats','links:ban','links:delete'),
  NOW(), NOW()
);
```

Usa el **token plano** en las peticiones (el backend compara por hash).

---

## API

> Revisa `openapi/shortener.yaml` si lo incluyes en el repo; a continuación un resumen práctico.

**Autenticación**: header `X-Api-Token: <token>`.

### Crear enlace
`POST /api/v1/links`
```json
{
  "url": "https://example.com/page",
  "max_clicks": 100,
  "expires_at": "2026-01-01T00:00:00Z"
}
```
**200**:
```json
{
  "id": 123,
  "slug": "1ZAbcDeF_9dQwE",
  "url": "https://example.com/page"
}
```

### Mostrar enlace
`GET /api/v1/links/{id}` → **200** con datos del enlace.

### Borrar enlace
`DELETE /api/v1/links/{id}` → **204**.

### Banear enlace
`POST /api/v1/links/{id}/ban` → **204**.

### Stats (agregadas, *privacy-first*)
`GET /api/v1/links/{id}/stats?window=24h`  
Devuelve agregados que cumplan `k ≥ SHORTENER_K_ANON`.

---

## Métricas y salud

- **/metrics** (Prometheus):
  - `redirect_requests_total{result=...}`
  - `redirect_duration_seconds_bucket/sum/count`
- **/health**: *liveness/readiness* simple (DB/Redis opcionalmente ping).

---

## Rate limits
- **Creación**: `SHORTENER_MAX_CREATE_PER_MINUTE` por token/IP.
- **Resolución**: `SHORTENER_MAX_RESOLVE_PER_MINUTE` por IP.
- Implementado con *named limiters* de Laravel; respuestas 429 con *Retry-After*.

---

## Colas y analítica
- Los clics (solo en `GET`) encolan `IngestClickEvent` → cola `analytics`.
- En producción, ejecuta un **worker** dedicado:
```yaml
# en docker-compose.prod.yml
  worker:
    image: marcosnevot/shortener:latest
    env_file: ./shortener.env
    depends_on: [app, redis]
    command: php artisan queue:work --queue=analytics --sleep=1 --max-jobs=0 --max-time=0
    restart: unless-stopped
```

---

## CI/CD

### CI — GitHub Actions
Workflow: `.github/workflows/ci.yml`
- **Job 1 — tests**: instala deps, prepara `.env.testing`, corre `vendor/bin/phpunit -d memory_limit=512M`.
- **Job 2 — build & push**: `docker buildx build` con `./app/Dockerfile`, *cache gha*, *multi-stage*, *push*.

**Secrets necesarios** (Settings → Secrets and variables → *Actions* → **Secrets**):
- `DOCKERHUB_USERNAME`
- `DOCKERHUB_TOKEN` (PAT)

**Variables (opcional)**:
- `DOCKERHUB_REPO=marcosnevot/shortener`

### CD — Docker Compose
Directorio `deploy/`, archivos:
- `docker-compose.prod.yml`
- `shortener.env` (variables de entorno)

Comandos:
```bash
docker compose -f deploy/docker-compose.prod.yml pull
docker compose -f deploy/docker-compose.prod.yml up -d
docker compose -f deploy/docker-compose.prod.yml ps
docker compose -f deploy/docker-compose.prod.yml logs -f --tail=200
```

---

## Despliegue en producción
- Imagen: `marcosnevot/shortener:latest` (ajusta si usas otro registry).
- Servicios típicos: `app` (php-fpm), `nginx`/`caddy` (reverse proxy), `db` (mysql), `redis`, `worker` (colas).
- **Primer arranque**:
  - Migraciones se ejecutan automáticamente (ver `CMD` del Dockerfile).
  - `storage:link` y `optimize` también se ejecutan on-start.
- **TLS**: Terminar en el reverse proxy (recomendado).
- **Backups**: volumen de MySQL y copia de seguridad del `APP_KEY` y `SHORTENER_HMAC_KEY`.

---

## Operación (runbooks)

### 1) Rotación de `SHORTENER_HMAC_KEY`
- Genera nueva clave (32 bytes base64).
- Despliega con *doble firma* temporal si implementas compatibilidad; si no, activa solo para slugs nuevos.
- No invalida slugs existentes si se mantienen ambas claves durante ventana de transición.

### 2) Alta latencia en `/ {slug }`
- Revisa Redis (latencias) y DB (índices en `links.id`).
- Activa `Cache::remember` (ya incluido) y ajusta TTL si es necesario.
- Observa `redirect_duration_seconds_*` en Prometheus.

### 3) Abuso de resolución
- Verifica *rate limits* y *ban* de enlaces.
- Ajusta `SHORTENER_MAX_RESOLVE_PER_MINUTE`.

### 4) DR (recuperación)
- Restaura backup de MySQL.
- Variables sensibles desde *vault* o gestor de secretos.
- Reconstruye imagen si es necesario y relanza `docker compose`.

---

## Roadmap
- [ ] OpenAPI 3.1 actualizado y publicado (repo `openapi/`).
- [ ] Panel de estadísticas (agregadas, *k-anon*).
- [ ] Export de métricas a Pushgateway (opcional).
- [ ] Canary releases para worker de analítica.

---

## Licencia
MIT 

---

### Snippets útiles

**Generar APP_KEY:**
```bash
docker compose exec app php artisan key:generate --show
```

**Probar tests localmente:**
```bash
docker compose exec app ./vendor/bin/phpunit -d memory_limit=512M
```

**Re-empaquetar imagen local (debug):**
```bash
docker build -t marcosnevot/shortener:dev -f app/Dockerfile app
```

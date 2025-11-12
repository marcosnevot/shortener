# OBSERVABILITY.md — Shortener

Guía práctica para **observar, medir y diagnosticar** el proyecto en **local** y en **CI** (y, opcionalmente, en despliegues que puedas hacer en el futuro). Cubre *health checks*, métricas (formato Prometheus), logs, paneles y reglas de alerta de ejemplo, además de pequeños runbooks.

> **Stack**: Laravel 12 (PHP‑FPM), MySQL 8, Redis 7. Servicio `Metrics` propio que persiste contadores e histogramas en Redis y expone `/metrics` en texto Prometheus. Middleware de cabeceras de seguridad activado. CI con GitHub Actions ejecutando tests y (opcional) build & push de imagen Docker si existen *secrets* de Docker Hub.

---

## 1) Health checks

### 1.1 Endpoint
- **`GET /health`** devuelve `200` con un JSON mínimo cuando las dependencias básicas están OK.
- **Payload sugerido**:
  ```json
  {
    "status": "ok",
    "time": "2025-01-01T12:00:00Z",
    "checks": {
      "db": "ok",
      "redis": "ok",
      "queue": "ok"
    }
  }
  ```
- *Fail‑fast*: si la conectividad con DB/Redis falla, responder `503`.

> En local lo puedes usar para *smoke checks* y en CI para comprobaciones rápidas con `curl`.

### 1.2 Health local rápido
```bash
curl -s http://localhost:8080/health | jq .
```

---

## 2) Métricas (formato Prometheus)

### 2.1 Diseño
- Implementación en `App\Services\Metrics` (`MetricsContract`):
  - **Counters** → `counterInc($name, array $labels = [], int|float $value = 1)`
  - **Histograms** → `histogramObserve($name, float $seconds, array $labels = [], array $buckets = null)` (buckets acumulativos)
- Claves en Redis:
  - Contadores: `metrics:cnt:{name}:{labelKey}`
  - Histogramas: `metrics:hist:{name}:{le}:{labelKey}` (+ `:sum:` y `:count:`)
  - Índices auxiliares para enumerar *names*, *buckets* y *label sets* (evita `SCAN` en *hot path*).
- **`/metrics`**:
  - Lee Redis y **renderiza** el formato de texto de Prometheus (HELP/TYPE, *samples*, *labels*).

> En CI se usa una `SHORTENER_HMAC_KEY` dummy y rutas temporales para *view/cache* a fin de satisfacer las pruebas.

### 2.2 Métricas actuales

1) **`redirect_requests_total`** (counter)  
**Labels**: `result ∈ {ok, bad_slug, bad_sig, not_found, banned, expired, limit_reached}`  
Se incrementa al finalizar cada intento de resolución/redirect.

2) **`redirect_duration_seconds`** (histogram)  
**Labels**: `result="ok"` (por defecto medimos la ruta exitosa)  
**Buckets** por defecto: `[0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, +Inf]`

> Convención: *lower_snake_case*, e incluir unidad en el nombre (p. ej. `_seconds`), y documentar **cada label** para evitar explosión de cardinalidad.

### 2.3 Salida de ejemplo
```
# HELP redirect_requests_total Total redirect requests by result
# TYPE redirect_requests_total counter
redirect_requests_total{result="ok"} 123
redirect_requests_total{result="bad_sig"} 4
redirect_requests_total{result="expired"} 2

# HELP redirect_duration_seconds Redirect duration in seconds
# TYPE redirect_duration_seconds histogram
redirect_duration_seconds_bucket{result="ok",le="0.1"} 100
redirect_duration_seconds_bucket{result="ok",le="0.25"} 120
redirect_duration_seconds_bucket{result="ok",le="0.5"} 122
redirect_duration_seconds_bucket{result="ok",le="+Inf"} 123
redirect_duration_seconds_sum{result="ok"} 10.42
redirect_duration_seconds_count{result="ok"} 123
```

### 2.4 *Scrape* con Prometheus (opcional)
Si quieres ver las métricas con Prometheus/Grafana **en local**, crea un fichero `prometheus.yaml` mínimo:

```yaml
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: shortener
    metrics_path: /metrics
    static_configs:
      - targets: ["host.docker.internal:8080"]  # si Prometheus está en Docker
      # - targets: ["localhost:8080"]          # si Prometheus corre nativo
```

Y levántalo (contenedor rápido):
```bash
docker run --rm -p 9090:9090 -v ${PWD}/prometheus.yaml:/etc/prometheus/prometheus.yml prom/prometheus
# Abre http://localhost:9090 y busca redirect_requests_total
```

> Si usas WSL/Windows, `host.docker.internal` te simplifica el acceso desde contenedores a tu host.

### 2.5 SLOs orientativos
- **p95 latency** de redirect: **< 50 ms** (en el mismo DC) o **< 90 ms** (edge).
- **Error ratio** redirect (resultados *bad_*, `expired`, `not_found`, `limit_reached`): **< 0.1%**.
- **Disponibilidad** de `/health`: **≥ 99.9%** mensual.
- **Lag de cola** (si activas analítica asíncrona): **p95 < 2 s**.

**PromQL útil**:
```promql
# p95 (ventana 5m)
histogram_quantile(0.95, sum by (le) (rate(redirect_duration_seconds_bucket[5m])))

# Error ratio (5m)
sum(rate(redirect_requests_total{result=~"bad_.*|expired|not_found|limit_reached"}[5m]))
/
sum(rate(redirect_requests_total[5m]))

# Picos de firmas inválidas (key mismatch / tampering)
rate(redirect_requests_total{result="bad_sig"}[5m])
```

---

## 3) Alertas (ejemplos)

> Úsalas como *plantillas* si montas Prometheus + Alertmanager. Ajusta umbrales a tu tráfico.

**Latencia p95 alta**
```yaml
groups:
- name: shortener-latency
  rules:
  - alert: RedirectLatencyHighP95
    expr: histogram_quantile(0.95, sum by (le) (rate(redirect_duration_seconds_bucket[5m]))) > 0.09
    for: 10m
    labels: {severity: page}
    annotations:
      summary: "p95 redirect latency > 90ms"
      description: "Revisar DB/Redis, saturación Nginx/PHP-FPM, o red."
```

**Error ratio alto**
```yaml
- name: shortener-errors
  rules:
  - alert: RedirectErrorRatioHigh
    expr: |
      sum(rate(redirect_requests_total{result=~"bad_.*|expired|not_found|limit_reached"}[10m]))
      / ignoring(result)
      sum(rate(redirect_requests_total[10m])) > 0.01
    for: 15m
    labels: {severity: page}
    annotations:
      summary: "Redirect error ratio > 1%"
      description: "Buscar picos de bad_sig (clave?), not_found (borrados), expired o limit_reached."
```

**Pico de firmas inválidas**
```yaml
- name: shortener-anomalies
  rules:
  - alert: BadSignatureSpike
    expr: rate(redirect_requests_total{result="bad_sig"}[5m]) > 0.2
    for: 10m
    labels: {severity: ticket}
    annotations:
      summary: "Aumento de firmas inválidas"
      description: "Posible desajuste de SHORTENER_HMAC_KEY o tráfico malicioso."
```

---

## 4) Logs

### 4.1 Formato
- En producción (si lo montas) prioriza **JSON logs** para parseo automático (Loki/ELK/CloudWatch). Ejemplo:
  ```json
  {"ts":"2025-01-01T12:00:00.000Z","level":"info","msg":"redirect","slug":"AbC_xYz12","result":"ok","ip":"203.0.113.10","ua":"Mozilla/5.0"}
  ```
- **Evita PII**. No loguees URLs con *tokens* u otros secretos.

### 4.2 Laravel
- `.env` típico:
  ```dotenv
  LOG_CHANNEL=stack
  LOG_STACK=single
  LOG_LEVEL=info
  ```
- Si usas JSON, define un *channel* específico en `config/logging.php`.
- Rotación a nivel plataforma (Docker / sistema) o usa el canal `daily` con retención.

### 4.3 Campos útiles
- `request_id` (si lo inyectas por request)
- `result`, `slug`, `link_id`, `ip`, `ua`, `referrer`
- Para *jobs*: `job`, `queue`, `duration_ms`, `result`

---

## 5) Dashboards (Grafana) — sugerencias

Crea **Shortener / Overview** con paneles:

1. **Tráfico por resultado**: `sum by (result) (rate(redirect_requests_total[5m]))`
2. **Latencias p50/p90/p95/p99** con `histogram_quantile(...)` sobre `redirect_duration_seconds_bucket`
3. **Error ratio**
4. **Evolución por resultado** (apilado)
5. **Bad signature rate**
6. **Limit hits**: `rate(redirect_requests_total{result="limit_reached"}[5m])`
7. **DB/Redis y CPU/mem** (exporters) / cAdvisor
8. **Cola analytics** (si la activas)

---

## 6) *Tracing* (opcional)

Si necesitas *end‑to‑end tracing*:
- OpenTelemetry (lib/ext PHP) + OTEL Collector.
- Vars típicas:
  ```dotenv
  OTEL_SERVICE_NAME=shortener
  OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4317
  OTEL_TRACES_SAMPLER=parentbased_traceidratio
  OTEL_TRACES_SAMPLER_ARG=0.05
  ```
- Empieza con *sampling* 1–5% y súbelo en incidentes.

---

## 7) Runbooks (rápidos)

### 7.1 Latencia alta
- Verifica Redis/DB y CPU.
- Revisa saturación de Nginx/PHP‑FPM (workers, *opcache*).
- Comprueba *egress* (DNS, TLS al destino si proxéas).

### 7.2 Picos de `bad_sig`
- Asegura que `SHORTENER_HMAC_KEY` es uniforme en instancias.
- Confirma que no hay doble codificación de slug o manipulación en el proxy/CDN.

### 7.3 Muchos `not_found`/`expired`
- Revisa ciclo de vida del enlace y *auto‑cleanup*.
- Valida *time skew* entre nodos/containers.

### 7.4 Backlog en colas (analytics)
- Escala *workers*; revisa *upserts* lentos; detecta *hot keys*.

---

## 8) Local & CI

### 8.1 Local
```bash
# Métricas
curl -s http://localhost:8080/metrics | head -n 30

# Health
curl -s http://localhost:8080/health | jq .
```

### 8.2 CI
- PHPUnit valida cabeceras, *redirect semantics* (HEAD no consume clic), *rate‑limits* y métricas básicas.
- El pipeline prepara `.env.testing` y rutas temporales para evitar errores de *view cache*.

---

## 9) Seguridad de `/metrics`
- Si lo publicas, restringe por red (IP allowlist) o **Basic Auth** en el *reverse proxy*.
- Nunca metas identificadores de usuario en *labels*.
- Mantén acotada la cardinalidad de *labels* y buckets.

---

## 10) Cambio controlado de métricas

Al añadir una métrica nueva:
1. Define **nombre, unidad, labels** y expectativa de cardinalidad.
2. Añádela en `Metrics`, documenta aquí.
3. Actualiza paneles/alertas relacionados.
4. Despliega y verifica (canary si aplica).

---

**Notas de portafolio**  
Este repositorio está preparado para funcionar **en local** y exhibir *pruebas, métricas y salud* sin necesidad de un despliegue público. Si más adelante decides exponerlo, las secciones opcionales (Prometheus/Grafana/Alertmanager) te sirven de guía rápida.

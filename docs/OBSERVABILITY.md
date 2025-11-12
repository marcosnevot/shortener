# Observability Guide — URL Shortener

This document describes how we **observe, measure, and diagnose** the shortener in all environments
(local, CI, and production). It covers health checks, metrics (Prometheus), logs, alerts,
dashboards, and quick runbooks.

> Stack recap: Laravel 12 (PHP‑FPM), MySQL, Redis, Nginx proxy, Docker. Custom `Metrics` service
using Redis for counters/histograms and a `/metrics` endpoint to expose Prometheus format.
Security headers middleware enabled. CI runs tests and builds/pushes Docker images.

---

## 1) Health checks

### 1.1 Endpoint
- **`GET /health`** returns `200` with a small JSON payload when core dependencies are OK.
- Minimal suggested payload:
  ```json
  {
    "status":"ok",
    "time":"2025-01-01T12:00:00Z",
    "checks":{
      "db":"ok",
      "redis":"ok",
      "queue":"ok"
    }
  }
  ```
- Fail fast: if DB/Redis connectivity fails, reply `503`.

### 1.2 Nginx
- Optionally route `/health` directly to PHP‑FPM (no auth/caching).
- Use it for external uptime monitoring (e.g. UptimeRobot, StatusCake) and for container
  **HEALTHCHECK** (see below).

### 1.3 Container healthcheck (optional)
Add to the production image or compose file (not mandatory but recommended):
```dockerfile
HEALTHCHECK --interval=30s --timeout=3s --retries=10 CMD   wget -qO- http://127.0.0.1/health | grep -q '"status":"ok"' || exit 1
```

---

## 2) Metrics (Prometheus)

### 2.1 Design
- We use `App\Services\Metrics` (implements `MetricsContract`) to record:
  - **Counters**: `counterInc($name, $labels = [], $value = 1)` stored in Redis.
  - **Histograms**: `histogramObserve($name, $seconds, $labels = [], $buckets = [...])`
    stored as cumulative buckets in Redis.
- Keys in Redis:
  - Counters: `metrics:cnt:{name}:{labelKey}`
  - Histograms: `metrics:hist:{name}:{le}:{labelKey}` (+ `:sum:` and `:count:`)
  - Indices to enumerate names, buckets and label sets (avoid `SCAN` in hot path).
- The `/metrics` endpoint:
  - **Reads** the Redis keys and **renders** Prometheus text format.
  - Exposes counters and histogram buckets with labels decoded from the `labelKey`.

> Note: In CI we inject a dummy HMAC key and temporary view/cache paths to satisfy tests.

### 2.2 Metric names & labels
Current metrics used by the app:

1. **`redirect_requests_total`** (counter)  
   Labels: `result ∈ {ok,bad_slug,bad_sig,not_found,banned,expired,limit_reached}`  
   When a redirect request completes, we increment the appropriate label.

2. **`redirect_duration_seconds`** (histogram)  
   Labels: `result ∈ {ok}` (we only time success path; extend if you need)  
   Default buckets: `[0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, +Inf]`

3. **(Planned / optional)** analytics ingestion:
   - `clicks_ingest_total{result="ok|error"}` (counter)
   - `clicks_ingest_duration_seconds{stage="persist|classify"}` (histogram)

> Keep names **lower_snake_case**, units in the name (e.g. `_seconds`) for histograms/gauges,
> and **document each label** to avoid cardinality blowups.

### 2.3 Sample Prometheus output
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

### 2.4 Prometheus scrape config
Add a job to your Prometheus server:
```yaml
scrape_configs:
  - job_name: 'shortener'
    scrape_interval: 15s
    metrics_path: /metrics
    static_configs:
      - targets: ['shortener-prod.your-domain.com']  # Nginx → PHP-FPM
```
If you run multiple instances behind a load balancer, prefer **per‑instance** scraping
(e.g., scrape the service discovery targets or node IPs) to avoid metric collisions.

### 2.5 SLOs (targets)
- **p95 redirect latency** (`/resolve` path): **< 50ms** (in‑DC) or **< 90ms** (edge).  
- **Error rate** for redirect (non‑2xx/3xx or 404 results due to bad slug/sig): **< 0.1%**.
- **Availability** for `/health`: **≥ 99.9%** monthly.
- **Queue lag** for analytics ingestion: **p95 < 2s** from click to persisted event.

PromQL examples:
```promql
# p95 latency (5m window)
histogram_quantile(0.95, sum by (le) (rate(redirect_duration_seconds_bucket[5m])))

# Redirect error ratio (5m)
sum(rate(redirect_requests_total{result=~"bad_.*|expired|not_found|limit_reached"}[5m]))
/
sum(rate(redirect_requests_total[5m]))

# Sudden spike of bad signatures (possible tampering or key mismatch)
rate(redirect_requests_total{result="bad_sig"}[5m])
```

---

## 3) Alerts (Prometheus/Alertmanager)

> Tune thresholds to your traffic profile; the values below are safe starting points.

### 3.1 Latency SLO burn
```yaml
groups:
- name: shortener-latency
  rules:
  - alert: RedirectLatencyHighP95
    expr: histogram_quantile(0.95, sum by (le) (rate(redirect_duration_seconds_bucket[5m]))) > 0.09
    for: 10m
    labels: {severity: page}
    annotations:
      summary: "p95 redirect latency is high (>90ms)"
      description: "Investigate DB/Redis, Nginx/PHP-FPM saturation, or upstream network."
```

### 3.2 Error ratio
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
      description: "Look for bad_sig spikes (key?), not_found (deleted), expired, or limit_reached."
```

### 3.3 Bad signature spike
```yaml
- name: shortener-anomalies
  rules:
  - alert: BadSignatureSpike
    expr: rate(redirect_requests_total{result="bad_sig"}[5m]) > 0.2
    for: 10m
    labels: {severity: ticket}
    annotations:
      summary: "Spike in bad signatures"
      description: "Possible HMAC key mismatch (deploy?) or malicious traffic."
```

---

## 4) Logs

### 4.1 Format
- Prefer **JSON logs** in production for machine parsing (Loki/ELK/CloudWatch). Example line:
  ```json
  {"ts":"2025-01-01T12:00:00.000Z","level":"info","msg":"redirect","slug":"AbC_xYz12","result":"ok","ip":"203.0.113.10","ua":"Mozilla/5.0"}
  ```
- Avoid logging PII/sensitive data. **Do not log full URLs with secrets**.

### 4.2 Laravel config
- In `.env` (prod):
  ```env
  LOG_CHANNEL=stack
  LOG_STACK=single
  LOG_LEVEL=info
  ```
- If you wire a JSON handler, set it in `config/logging.php` under `channels`.
- Rotate logs at the platform level (Docker or systemd) or use `daily` channel with retention.

### 4.3 Useful log fields
- `request_id` (if you inject one per request)
- `result`, `slug`, `link_id`, `ip`, `ua`, `referrer`
- For jobs: `job`, `queue`, `duration_ms`, `result`

---

## 5) Dashboards (Grafana)

Create a dashboard `Shortener / Overview` with panels:

1. **Traffic**: `sum by (result) (rate(redirect_requests_total[5m]))`
2. **Latency p50/p90/p95/p99**: use `histogram_quantile(...)` on `redirect_duration_seconds_bucket`
3. **Error ratio**: bad results / total
4. **Top results over time** stacked
5. **Bad signature rate** (lead indicator for key issues)
6. **Max clicks limit hits**: `rate(redirect_requests_total{result="limit_reached"}[5m])`
7. **DB/Redis saturation** (from exporters) and container CPU/mem (cAdvisor/node exporter)
8. **Queue depth / processing rate** for analytics (if/when implemented)

---

## 6) Tracing (optional)

If you need end‑to‑end traces:
- Use PHP OpenTelemetry auto‑instrumentation (composer lib + extension) and export to OTEL collector.
- Set envs like:
  ```env
  OTEL_SERVICE_NAME=shortener
  OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4317
  OTEL_TRACES_SAMPLER=parentbased_traceidratio
  OTEL_TRACES_SAMPLER_ARG=0.05
  ```
- Start with sampling 1–5% and increase for incidents.

---

## 7) Runbooks (quick hints)

### 7.1 High latency
- Check Redis/DB connectivity and CPU saturation.
- Inspect Nginx/PHP‑FPM worker saturation (increase workers, tune opcache).
- Verify network egress (DNS, TLS to destinations if you proxy).

### 7.2 Bad signature spikes
- Validate `SHORTENER_HMAC_KEY` value across instances/jobs.
- Confirm no double‑encoding of slug or URL manipulation at proxy/CDN.

### 7.3 Many `not_found`/`expired`
- Confirm link lifecycle; review auto‑cleanup jobs; validate time skew in nodes.

### 7.4 Queue backlog (analytics)
- Scale consumers; examine slow queries on aggregation upserts; look for hot keys.

---

## 8) Local & CI usage

### 8.1 Local
```bash
# Metrics
curl -s http://localhost:8080/metrics | head -n 30
# Health
curl -s http://localhost:8080/health | jq .
```

### 8.2 Load testing (smoke)
`k6` example (optional):
```js
import http from 'k6/http';
import { sleep } from 'k6';
export const options = { vus: 20, duration: '30s' };
export default function () {
  // Replace with an existing short link
  http.get('http://localhost:8080/AbC_xYz12');
  sleep(0.1);
}
```

---

## 9) Security of /metrics
- Restrict exposure of `/metrics` to internal networks or protect behind Basic Auth or IP allowlist.
- Do not include user identifiers in label values.
- Keep bucket/label cardinality bounded.

---

## 10) Change management

When adding a new metric:
1. Define **name, unit, labels** and cardinality expectation.
2. Add it in code via `Metrics` service; document here.
3. Update Grafana panels and alert rules if relevant.
4. Deploy and verify on a canary instance before rolling out cluster‑wide.

---

**That’s it.** You now have a complete view of the app through health checks, metrics, logs,
alerts, and dashboards. Keep SLOs tight, cardinality bounded, and runbooks short.

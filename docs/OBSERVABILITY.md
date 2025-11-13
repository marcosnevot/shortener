# OBSERVABILITY.md — Shortener

Practical guide to **observing, measuring and troubleshooting** the project in **local** and in **CI** (and, optionally, in any deployments you may do in the future). It covers health checks, metrics (Prometheus text format), logs, dashboards and example alert rules, plus short runbooks.

> **Stack**: Laravel 12 (PHP‑FPM), MySQL 8, Redis 7. Custom `Metrics` service that persists counters and histograms in Redis and exposes `/metrics` in Prometheus text format. Security headers middleware enabled. CI with GitHub Actions running tests and (optionally) building & pushing the Docker image if Docker Hub secrets are configured.

---

## 1) Health checks

### 1.1 Endpoint
- **`GET /health`** returns `200` with a minimal JSON payload when the basic dependencies are OK.
- **Suggested payload**:
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
- *Fail-fast*: if connectivity to DB/Redis fails, respond with `503`.

> Locally you can use it for *smoke checks* and in CI for quick checks with `curl`.

### 1.2 Quick local health
```bash
curl -s http://localhost:8080/health | jq .
```

---

## 2) Metrics (Prometheus format)

### 2.1 Design
- Implementation in `App\Services\Metrics` (`MetricsContract`):
  - **Counters** → `counterInc($name, array $labels = [], int|float $value = 1)`
  - **Histograms** → `histogramObserve($name, float $seconds, array $labels = [], array $buckets = null)` (cumulative buckets)
- Keys in Redis:
  - Counters: `metrics:cnt:{name}:{labelKey}`
  - Histograms: `metrics:hist:{name}:{le}:{labelKey}` (+ `:sum:` and `:count:`)
  - Auxiliary indexes to enumerate *names*, *buckets* and *label sets* (avoids using `SCAN` on the hot path).
- **`/metrics`**:
  - Reads Redis and **renders** Prometheus text format (HELP/TYPE, samples, labels).

> In CI a dummy `SHORTENER_HMAC_KEY` is used and temporary paths for *view/cache* are set so tests can run.

### 2.2 Current metrics

1) **`redirect_requests_total`** (counter)  
**Labels**: `result ∈ {ok, bad_slug, bad_sig, not_found, banned, expired, limit_reached}`  
Incremented at the end of every resolution/redirect attempt.

2) **`redirect_duration_seconds`** (histogram)  
**Labels**: `result="ok"` (by default we measure the successful path)  
**Default buckets**: `[0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, +Inf]`

> Convention: *lower_snake_case*, include the unit in the name (e.g. `_seconds`), and document **every label** to avoid a cardinality explosion.

### 2.3 Sample output
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

### 2.4 Scraping with Prometheus (optional)
If you want to see the metrics with Prometheus/Grafana **locally**, create a minimal `prometheus.yaml` file:

```yaml
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: shortener
    metrics_path: /metrics
    static_configs:
      - targets: ["host.docker.internal:8080"]  # if Prometheus is in Docker
      # - targets: ["localhost:8080"]          # if Prometheus runs natively
```

And start it (quick container):
```bash
docker run --rm -p 9090:9090 -v ${PWD}/prometheus.yaml:/etc/prometheus/prometheus.yml prom/prometheus
# Open http://localhost:9090 and search for redirect_requests_total
```

> If you use WSL/Windows, `host.docker.internal` simplifies access from containers to your host.

### 2.5 Suggested SLOs
- **p95 latency** for redirects: **< 50 ms** (same DC) or **< 90 ms** (edge).
- **Redirect error ratio** (results *bad_*, `expired`, `not_found`, `limit_reached`): **< 0.1%**.
- **Availability** of `/health`: **≥ 99.9%** monthly.
- **Queue lag** (if you enable asynchronous analytics): **p95 < 2 s**.

**Useful PromQL**:
```promql
# p95 (5m window)
histogram_quantile(0.95, sum by (le) (rate(redirect_duration_seconds_bucket[5m])))

# Error ratio (5m)
sum(rate(redirect_requests_total{result=~"bad_.*|expired|not_found|limit_reached"}[5m]))
/
sum(rate(redirect_requests_total[5m]))

# Spikes of invalid signatures (key mismatch / tampering)
rate(redirect_requests_total{result="bad_sig"}[5m])
```

---

## 3) Alerts (examples)

> Use these as *templates* if you set up Prometheus + Alertmanager. Adjust thresholds to your traffic.

**High p95 latency**
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
      description: "Check DB/Redis, Nginx/PHP-FPM saturation, or network."
```

**High error ratio**
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
      description: "Look for spikes in bad_sig (key?), not_found (deletions), expired or limit_reached."
```

**Spike of invalid signatures**
```yaml
- name: shortener-anomalies
  rules:
  - alert: BadSignatureSpike
    expr: rate(redirect_requests_total{result="bad_sig"}[5m]) > 0.2
    for: 10m
    labels: {severity: ticket}
    annotations:
      summary: "Increase in invalid signatures"
      description: "Possible SHORTENER_HMAC_KEY mismatch or malicious traffic."
```

---

## 4) Logs

### 4.1 Format
- In production (if you set it up) prefer **JSON logs** for automatic parsing (Loki/ELK/CloudWatch). Example:
  ```json
  {"ts":"2025-01-01T12:00:00.000Z","level":"info","msg":"redirect","slug":"AbC_xYz12","result":"ok","ip":"203.0.113.10","ua":"Mozilla/5.0"}
  ```
- **Avoid PII**. Do not log URLs with *tokens* or other secrets.

### 4.2 Laravel
- Typical `.env`:
  ```dotenv
  LOG_CHANNEL=stack
  LOG_STACK=single
  LOG_LEVEL=info
  ```
- If you use JSON, define a specific *channel* in `config/logging.php`.
- Rotate logs at platform level (Docker / system) or use the `daily` channel with retention.

### 4.3 Useful fields
- `request_id` (if you inject one per request)
- `result`, `slug`, `link_id`, `ip`, `ua`, `referrer`
- For *jobs*: `job`, `queue`, `duration_ms`, `result`

---

## 5) Dashboards (Grafana) — suggestions

Create **Shortener / Overview** with panels:

1. **Traffic by result**: `sum by (result) (rate(redirect_requests_total[5m]))`
2. **Latencies p50/p90/p95/p99** with `histogram_quantile(...)` over `redirect_duration_seconds_bucket`
3. **Error ratio**
4. **Trend by result** (stacked)
5. **Bad signature rate**
6. **Limit hits**: `rate(redirect_requests_total{result="limit_reached"}[5m])`
7. **DB/Redis and CPU/mem** (exporters) / cAdvisor
8. **Analytics queue** (if you enable it)

---

## 6) Tracing (optional)

If you need *end‑to‑end tracing*:
- OpenTelemetry (PHP lib/ext) + OTEL Collector.
- Typical vars:
  ```dotenv
  OTEL_SERVICE_NAME=shortener
  OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4317
  OTEL_TRACES_SAMPLER=parentbased_traceidratio
  OTEL_TRACES_SAMPLER_ARG=0.05
  ```
- Start with a 1–5% *sampling* rate and increase it during incidents.

---

## 7) Runbooks (quick)

### 7.1 High latency
- Check Redis/DB and CPU.
- Review Nginx/PHP‑FPM saturation (workers, *opcache*).
- Check *egress* (DNS, TLS to the target if you proxy).

### 7.2 Spikes in `bad_sig`
- Ensure `SHORTENER_HMAC_KEY` is consistent across instances.
- Confirm there is no double encoding of the slug or manipulation in the proxy/CDN.

### 7.3 Many `not_found`/`expired`
- Review link lifecycle and *auto‑cleanup*.
- Validate *time skew* between nodes/containers.

### 7.4 Backlog in queues (analytics)
- Scale *workers*; review slow *upserts*; detect *hot keys*.

---

## 8) Local & CI

### 8.1 Local
```bash
# Metrics
curl -s http://localhost:8080/metrics | head -n 30

# Health
curl -s http://localhost:8080/health | jq .
```

### 8.2 CI
- PHPUnit validates headers, *redirect semantics* (HEAD does not consume a click), *rate‑limits* and basic metrics.
- The pipeline prepares `.env.testing` and temporary paths to avoid *view cache* errors.

---

## 9) Security of `/metrics`
- If you expose it, restrict by network (IP allowlist) or **Basic Auth** in the reverse proxy.
- Never put user identifiers into *labels*.
- Keep label and bucket cardinality under control.

---

## 10) Controlled changes to metrics

When adding a new metric:
1. Define **name, unit, labels** and expected cardinality.
2. Add it to `Metrics`, and document it here.
3. Update related dashboards/alerts.
4. Deploy and verify (canary if applicable).

---

**Portfolio notes**  
This repository is prepared to work **locally** and to expose *tests, metrics and health* without needing a public deployment. If you later decide to expose it, the optional sections (Prometheus/Grafana/Alertmanager) serve as a quick guide.

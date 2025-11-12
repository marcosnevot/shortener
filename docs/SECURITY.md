# Security

## Superficie y principios
- **Negar por defecto**: redirecciones solo válidas si firma/estado/límites/expiración OK.
- **Anti-enumeración**: mismos códigos 404 para casos inválidos (slug, firma, ban, expirado).
- **Whitelisting**: `SHORTENER_ALLOWED_SCHEMES` y `SHORTENER_DOMAIN_WHITELIST`.
- **Tokens API**: hash almacenado (`token_hash`), `scopes` por acción.

## Encabezados
- `SecurityHeaders` middleware:
  - `Content-Security-Policy` (CSP estricta)
  - `Strict-Transport-Security` (HSTS)
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `X-Content-Type-Options: nosniff`
  - Redirecciones con `Cache-Control: no-store`

## Claves & secretos
- `SHORTENER_HMAC_KEY` (32B). Rotación planificada: requiere doble validación temporal o regeneración de slugs.
- Secrets en **GitHub Actions** y **Docker** (no en repo).

## Límites
- Rate-limits por minuto en creación/resolución (env).
- Máximo por link: campo `max_clicks` con incremento **atómico**.

## Datos & Privacidad
- Sin PII en analytics. Se envía a cola `analytics` un evento mínimo (fecha, UA, referrer, ip).
- Aggregación en `clicks_agg` con **k-anonymity** (parametrizable).

## Testing
- Tests funcionales cubren:
  - HEAD no consume click.
  - Límite `max_clicks` atómico.
  - Encabezados de seguridad presentes en panel.


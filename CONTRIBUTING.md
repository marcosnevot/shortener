# Guía de Contribución (CONTRIBUTING.md)

¡Gracias por tu interés en contribuir! Este documento describe el flujo de trabajo, estándares y herramientas del proyecto **Shortener** (acortador de URLs con firma HMAC y analítica *privacy‑first*).

> **Resumen del stack**: Laravel 12 (PHP 8.3), MySQL 8, Redis 7, Blade, colas (database/redis), Docker multi‑stage, GitHub Actions, Prometheus (/metrics), endpoint /health, límites y cabeceras de seguridad.

---

## Tabla de contenido
- [Código de conducta](#código-de-conducta)
- [Requisitos](#requisitos)
- [Preparación del entorno local](#preparación-del-entorno-local)
- [Estructura del repositorio](#estructura-del-repositorio)
- [Flujo de trabajo Git](#flujo-de-trabajo-git)
- [Estilo y calidad](#estilo-y-calidad)
- [Tests](#tests)
- [Commits y mensajes](#commits-y-mensajes)
- [Pull Requests](#pull-requests)
- [CI/CD](#cicd)
- [Documentación](#documentación)
- [Seguridad](#seguridad)
- [Reporte de vulnerabilidades](#reporte-de-vulnerabilidades)
- [Licencia](#licencia)

---

## Código de conducta
Adoptamos el [Contributor Covenant](https://www.contributor-covenant.org/). Sé respetuoso, profesional y empático. Incidencias graves pueden ser comunicadas por *GitHub Security Advisories* o a los Maintainers del proyecto.

---

## Requisitos
- **Docker Desktop** (o Podman) y **Docker Compose**.
- **Git**.
- (Opcional) PHP 8.3 + Composer si deseas ejecutar fuera de contenedor.

> Las versiones mínimas de servicios: MySQL 8, Redis 7, PHP 8.3. El entorno está preparado para levantarse con `docker compose` sin instalaciones locales adicionales.

---

## Preparación del entorno local
Clona el repositorio y levanta servicios:

```bash
# 1) Clonar
git clone https://github.com/<tu-usuario>/shortener.git
cd shortener

# 2) Entorno
cp .env.example .env  # o usa tu .env local
# Ajusta credenciales y claves HMAC en .env (ver ENV.md)

# 3) Levantar stack
docker compose up -d

# 4) Migrar/sembrar (primera vez)
docker compose exec app php artisan migrate --seed

# 5) Ejecutar la suite de tests
docker compose exec app ./vendor/bin/phpunit -d memory_limit=512M
```

> **Nota**: El proyecto expone `/health` y `/metrics`. Redis se usa para colas y métricas (contador + histogramas).

---

## Estructura del repositorio
- `app/` (código Laravel + **Dockerfile** runtime y CI)
- `deploy/` (plantillas de despliegue: `docker-compose.prod.yml`, `shortener.env`)
- `.github/workflows/ci.yml` (pipeline de tests + build/push de imagen)
- `config/shortener.php` (configuración propia: HMAC, límites, k‑anon)
- `app/Services/` (`SlugService`, `MetricsContract`, `Metrics`)
- `app/Http/Controllers/Web/RedirectController.php` (lógica HEAD‑safe, contador atómico, firma HMAC)
- `routes/`, `resources/views/`, `database/`, `tests/`

---

## Flujo de trabajo Git
- Rama principal: **main** (protegida).
- Crea ramas por cambio:
  - `feat/<breve-descripcion>`
  - `fix/<breve-descripcion>`
  - `chore/<breve-descripcion>`
  - `docs/<breve-descripcion>`

Ejemplo:
```bash
git checkout -b feat/limite-clicks-graceful
# ... cambios ...
git add .
git commit -m "feat: enforce atomic max_clicks with 404 when exceeded"
git push -u origin feat/limite-clicks-graceful
# Abre un Pull Request hacia main
```

> **No** hagas push directo a `main`. Todo cambio pasa por PR y CI.

---

## Estilo y calidad
- **PSR-12**. Mantén funciones pequeñas, nombres claros y manejo explícito de errores.
- Alineado con convenciones de Laravel (nombres de controladores, servicios, jobs).
- **Formateo**: si usas *laravel/pint* localmente, ejecuta `./vendor/bin/pint` antes de commitear (opcional pero recomendado).
- **Blade**: evita lógica compleja en vistas; delega a controladores/servicios.
- **Seguridad**: nunca exponer secretos en logs o excepciones. Usa `config()` / ENV.

---

## Tests
Ejecuta la suite completa:

```bash
docker compose exec app ./vendor/bin/phpunit -d memory_limit=512M
```

Cobertura recomendada: **≥ 80%** para nuevas funcionalidades (no bloqueante, pero se valora).

Cubre especialmente:
- **SlugService**: generación/parsing, firma HMAC.
- **RedirectController**: HEAD‑safe (no consume click), caducidad, *ban*, firma inválida, límite de clics atómico.
- **API** (crear/consultar/ban/borrar): límites de rate, dominios/esquemas permitidos.
- **SecurityHeaders** del panel.
- **Métricas**: contadores y histogramas (al menos *smoke tests* en rutas críticas).

Si introduces endpoints o lógica nueva, añade tests equivalentes. Los tests **no** deben depender de red externa.

---

## Commits y mensajes
Usa **Conventional Commits**:

- `feat: …` nueva funcionalidad
- `fix: …` corrección de bug
- `docs: …` documentación (README, ENV.md, etc.)
- `test: …` tests
- `refactor: …` refactor sin cambios funcionales
- `ci: …` cambios de pipeline/acciones
- `chore: …` tareas generales (dependencias, scripts)

Ejemplos:
```
feat: add k-anon aggregation and endpoint for stats
fix: avoid null HMAC key on CI by loading from config
ci: buildx with gha cache and dockerhub push
docs: add DEPLOYMENT and OBSERVABILITY guides
```

Commits pequeños y atómicos. Mensajes en **imperativo** y con contexto.

---

## Pull Requests
Criterios para abrir PR:
- Tests en verde localmente.
- Actualiza documentación si cambian variables, endpoints o seguridad.
- Describe **qué**, **por qué** y **cómo** probar. Si aplica, añade captura o logs de prueba.

**Checklist PR**:
- [ ] Cambios enfocados (un tema por PR).
- [ ] Sin secretos ni credenciales.
- [ ] `ENV.md`/`OBSERVABILITY.md` actualizados si aplica.
- [ ] Suite PHPUnit en verde.
- [ ] Revisión de *edge cases* (errores de firma, expiración, límites, cola caída).

Plantilla breve para la descripción:
```
### Resumen
Describe brevemente el cambio.

### Motivación
¿Por qué es necesario? ¿Qué problema resuelve?

### Cómo probar
Pasos, comandos y datos de prueba.

### Riesgos
Impacto en seguridad, latencia, almacenamiento o compatibilidad.
```

---

## CI/CD
- La acción `.github/workflows/ci.yml`:
  1. Ejecuta PHPUnit (entorno `app/`).
  2. Hace **build & push** de la imagen Docker (`latest`) a tu registro.
- Configura en GitHub **Settings ▸ Secrets and variables ▸ Actions**:
  - `DOCKERHUB_USERNAME` (o variables equivalentes para tu registro),
  - `DOCKERHUB_REPO` (p. ej. `tu_usuario/shortener`),
  - `DOCKERHUB_TOKEN` (token de acceso).

Errores típicos y remedios están documentados en `DEPLOYMENT.md`.

---

## Documentación
Si tu PR afecta a:
- Variables de entorno → actualiza **ENV.md** (+ ejemplos).
- Observabilidad → actualiza **OBSERVABILITY.md** (métricas, paneles).
- Overview/uso → actualiza **README.md**.

Añade entradas a **CHANGELOG.md** bajo `[Unreleased]` siguiendo *Keep a Changelog*.

---

## Seguridad
- **Claves y secretos**: nunca en commits. Usa ENV y secretos del sistema (Docker, CI).
- **HMAC**: `SHORTENER_HMAC_KEY` debe ser **secreta** y con suficiente entropía/Base64.
- **Rate limits**: mantén límites razonables para evitar abuso (`SHORTENER_MAX_*`).
- **Cabeceras**: no las relajes sin justificar. Panel con CSP, HSTS, X‑Frame‑Options, etc.
- **Logs**: no registrar datos sensibles (URLs completas si contienen secretos, IPs sin necesidad, etc.).
- **Colas**: si se caen, la redirección debe seguir siendo segura (perdida de analítica, nunca de seguridad).

Cambios que afecten a seguridad requieren una sección de **Riesgos** en el PR y pruebas específicas.

---

## Reporte de vulnerabilidades
No abras issues públicos para vulnerabilidades. Usa **GitHub Security Advisories** o contacta a los Maintainers. Proporciona detalles y pasos de reproducción si es posible.

---

## Licencia
Al contribuir, aceptas que tu contribución se licencie bajo la licencia del proyecto indicada en `LICENSE`.

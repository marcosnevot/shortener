# Deployment

## Overview

This project ships as a **Dockerized Laravel 12 (PHP‑FPM)** service, tested by **PHPUnit** and published automatically to **Docker Hub** via **GitHub Actions**. Runtime concerns (migrations, cache warm‑up, storage link) are handled by the container entry command.

> The app exposes **/health** and **/metrics** for liveness and Prometheus scraping. See `docs/OBSERVABILITY.md` for details.

---

## Prerequisites

- **Docker Engine** and **Docker Compose** installed on the target server.
- A Docker Hub repository: `docker.io/<tu_usuario>/shortener` (lowercase).
- GitHub repository with the workflow at `.github/workflows/ci.yml` (already added).
- GitHub Actions configuration (Repository → *Settings* → *Secrets and variables* → *Actions*):
  - **Secrets** (sensitive):
    - `DOCKERHUB_USERNAME` → tu usuario de Docker Hub.
    - `DOCKERHUB_TOKEN` → token de acceso de Docker Hub con permiso de *Write*.
  - **Variables** (no sensibles):
    - `DOCKER_IMAGE` → `docker.io/<tu_usuario>/shortener` (en minúsculas).

> Usa **Secrets** para credenciales y **Variables** para valores no sensibles. Evita subir `.env` al repositorio.

---

## CI/CD Flow (GitHub Actions)

1. Un **push a `main`** dispara el workflow.
2. El job **test** instala dependencias, prepara paths de cache para Blade y ejecuta **PHPUnit**.
3. El job **build-and-push** (si los tests pasan) construye con **Buildx**, usa caché de `gha` y **empuja** la imagen a **Docker Hub** con tag `latest`.
4. En el servidor, ejecutas `docker compose pull && up -d` para adoptar la última imagen.

Variables/claves esperadas por el workflow:
- `secrets.DOCKERHUB_USERNAME`
- `secrets.DOCKERHUB_TOKEN`
- `vars.DOCKER_IMAGE`

---

## First-Time Deployment (server)

> Opción recomendada: **mantener sólo la carpeta `deploy/`** en el servidor y gestionar `.env` allí. El resto vive en CI/CD.

1. **Copia** la carpeta `deploy/` al servidor (o clona el repo y conserva sólo `deploy/`).
2. Crea el archivo de entorno de producción:
   - `deploy/shortener.env` (ver plantilla y descripciones en `docs/ENV.md`).
3. Edita `deploy/docker-compose.prod.yml` y asegúrate de apuntar a tu imagen:
   ```yaml
   services:
     app:
       image: docker.io/<tu_usuario>/shortener:latest
       env_file: deploy/shortener.env
       # … otros parámetros (volúmenes, redes, etc.)
   ```
4. Despliegue inicial:
   ```bash
   docker compose -f deploy/docker-compose.prod.yml pull
   docker compose -f deploy/docker-compose.prod.yml up -d
   docker compose -f deploy/docker-compose.prod.yml ps
   docker compose -f deploy/docker-compose.prod.yml logs -f app
   ```
   La imagen ejecuta al arranque:
   - `php artisan storage:link || true`
   - `php artisan migrate --force`
   - `php artisan optimize`
   - Lanza **php-fpm** en primer plano.

> La imagen es **PHP‑FPM** (puerto 9000 interno). Colócala detrás de un reverse proxy (Nginx/Traefik).

---

## Reverse Proxy (Nginx) – ejemplo

> Si el proxy Nginx corre **en el host** y el contenedor expone 9000 en una red de Docker, puedes usar FastCGI a `app:9000` (ajusta `server_name` y rutas).

```nginx
server {
  listen 80;
  server_name short.yourdomain.tld;

  # Redirige a HTTPS en producción real
  # return 301 https://$host$request_uri;

  root /var/www/html/public; # No se usa en FastCGI con upstream remoto, se deja por compat.

  location / {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME /var/www/html/public/index.php;
    fastcgi_param PATH_INFO $fastcgi_path_info;
    fastcgi_pass app:9000; # nombre del servicio en la red de Compose
    fastcgi_read_timeout 60s;
  }

  # Archivos estáticos (si montas volumen con public/)
  location ~* \.(png|jpg|jpeg|gif|css|js|ico|svg)$ {
    try_files $uri /index.php?$query_string;
  }
}
```

> Alternativa: añadir un servicio `nginx` en `docker-compose.prod.yml` que apunte por `fastcgi_pass app:9000` y exponga 80/443.

---

## Upgrades

Tras cada push a `main` (la imagen `latest` se publica automáticamente):
```bash
docker compose -f deploy/docker-compose.prod.yml pull
docker compose -f deploy/docker-compose.prod.yml up -d
docker compose -f deploy/docker-compose.prod.yml ps
```

Para *roll forward* rápido ante fallos transitorios, repite `up -d` tras ajustar variables.

---

## Rollback

1. Identifica una etiqueta anterior en Docker Hub (por ejemplo, `1.0.0` si gestionas tags semánticos).
2. Cambia temporalmente en `deploy/docker-compose.prod.yml`:
   ```yaml
   image: docker.io/<tu_usuario>/shortener:1.0.0
   ```
3. Aplica:
   ```bash
   docker compose -f deploy/docker-compose.prod.yml up -d
   ```

> Vuelve a `latest` cuando la incidencia esté resuelta.

---

## Health & Observability

- **Health**: `GET /health`
- **Prometheus**: `GET /metrics`  
  Configura tu *scrape job* hacia la URL del servicio (ver `docs/OBSERVABILITY.md`).

---

## Security Notes

- Mantén `APP_KEY` y `SHORTENER_HMAC_KEY` **fuera** del repositorio. Cárgalos en `shortener.env`.
- No publiques `DOCKERHUB_TOKEN`. Usa **GitHub Secrets**.
- En producción, usa `APP_ENV=production` y `APP_DEBUG=false`.
- Restringe los **schemes** permitidos y la **whitelist de dominios** según tu política.

---

## Troubleshooting

**`invalid reference format: repository name must be lowercase`**  
Asegúrate de que `DOCKER_IMAGE` y `image:` están en minúsculas (e.g., `docker.io/marcosnevot/shortener`).

**`Could not open input file: artisan` durante `composer install` en Docker**  
Solución: en el *stage* `vendor` se ejecuta con `--no-scripts` para evitar `artisan package:discover` cuando aún no existe el árbol completo de la app. El *stage* final copia la app desde `vendor`.

**`chown: storage: No such file or directory` en build**  
El Dockerfile crea `storage` y `bootstrap/cache` si no existen:
```dockerfile
RUN mkdir -p storage bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache
```

**`authentication required` al hacer `docker compose pull`**  
Ejecuta `docker login` en el servidor o usa un **credential store**. Verifica políticas de *rate limit* del registry.

**Errores en migraciones al arrancar**  
Revisa variables de DB en `shortener.env`. Vuelve a ejecutar `up -d` tras validar conectividad a MySQL.

---

## Operational Checklist

- [ ] `deploy/shortener.env` creado y con claves robustas (ver `docs/ENV.md`).
- [ ] Reverse proxy/Nginx configurado hacia `app:9000`.
- [ ] Prometheus apuntando a `/metrics` si se usa observabilidad.
- [ ] Backups de la base de datos programados.
- [ ] Política de tags/rollback definida (opcional si usas solo `latest`).

---

## Commands Summary (server)

```bash
# Primer despliegue / actualización
docker compose -f deploy/docker-compose.prod.yml pull
docker compose -f deploy/docker-compose.prod.yml up -d
docker compose -f deploy/docker-compose.prod.yml logs -f app

# Ver estado
docker compose -f deploy/docker-compose.prod.yml ps

# Rollback a una etiqueta concreta
# (editar docker-compose.prod.yml -> image: docker.io/<tu_usuario>/shortener:<tag>)
docker compose -f deploy/docker-compose.prod.yml up -d
```

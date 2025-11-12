# Operations (Runbooks)

## Despliegue
- Ver docs/DEPLOYMENT.md.

## Rotación de SHORTENER_HMAC_KEY
**Actual**: single key. Opciones:
1) **Regenerar slugs** (corte controlado): pausar creación, cambiar clave, regenerar slugs y comunicar.
2) **Doble validación** (recomendado futuro): soportar OLD_KEY,NEW_KEY en config y aceptar ambas firmas durante ventana de rotación. *(A implementar si se requiere)*

## Baneo de enlaces abusivos
1) Marca is_banned = 1 en DB o vía endpoint /api/links/{id}/ban.
2) Caché se actualiza en la próxima resolución; invalidación manual opcional: cache:clear.

## Rendimiento alto en resolución
- Redis en la misma red/host de la app para minimizar latencia.
- Cache::remember(link:<id>) TTL 10 min; ajusta según tráfico.

## DR / Backups
- MySQL: snapshots + dumps (mysqldump).
- Redis: snapshot (RDB) si procede; métricas son reconstruibles, pero clicks_agg conviene conservar.

## GeoIP
- Actualiza GEOIP_DB cuando haya nueva base. Monta volumen sobre storage/app/geoip.

## Mantenimiento
- php artisan optimize:clear
- php artisan migrate --force
- Rotación de logs: delegada al runtime/container.

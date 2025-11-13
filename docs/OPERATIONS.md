# Operations (Runbooks)

## Rotation of SHORTENER_HMAC_KEY
**Current**: single key. Options:
1) **Regenerate slugs** (controlled cutover): pause creation, change the key, regenerate slugs and communicate.
2) **Dual validation** (recommended for the future): support OLD_KEY,NEW_KEY in config and accept both signatures during the rotation window. *(To be implemented if required)*

## Banning abusive links
1) Set is_banned = 1 in DB or via endpoint /api/links/{id}/ban.
2) Cache is updated on the next resolution; optional manual invalidation: cache:clear.

## High performance in resolution
- Redis on the same network/host as the app to minimize latency.
- Cache::remember(link:<id>) TTL 10 min; adjust according to traffic.

## DR / Backups
- MySQL: snapshots + dumps (mysqldump).
- Redis: snapshot (RDB) if appropriate; metrics can be rebuilt, but clicks_agg is worth preserving.

## GeoIP
- Update GEOIP_DB when a new database is available. Mount a volume over storage/app/geoip.

## Maintenance
- php artisan optimize:clear
- php artisan migrate --force
- Log rotation: delegated to the runtime/container.

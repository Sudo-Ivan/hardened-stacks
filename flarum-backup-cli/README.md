# flarum-backup

Export and restore Flarum data (database, config, assets, storage).

Zero third party Go dependencies. Bundled in the Flarum image as `flarum-backup`.

## Build

```
make check
make build
```

## Export

```
flarum-backup export -o /backups/forum.tar.gz
```

Skips volatile storage dirs (cache, sessions, logs, tmp, views, less, formatter, locale).

## Restore

```
flarum-backup restore -i /backups/forum.tar.gz --force
```

`--force` is required.

## Environment

| Variable | Default |
| --- | --- |
| `DB_HOST` | `mariadb` |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | `flarum` |
| `DB_USERNAME` | `flarum` |
| `DB_PASSWORD` | (required for db) |
| `FLARUM_HOME` | `/app` |
| `FLARUM_PERSIST_DIR` | `/persist` |
| `FLARUM_ASSETS_DIR` | `/app/public/assets` |
| `FLARUM_STORAGE_DIR` | `/app/storage` |

## In container

```
docker compose exec flarum flarum-backup export -o /tmp/forum.tar.gz
docker compose exec -T flarum flarum-backup restore -i /tmp/forum.tar.gz --force
```

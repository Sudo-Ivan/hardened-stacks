#!/bin/sh
set -eu

DATA_DIR="${DATA_DIR:-/data}"
mkdir -p "${DATA_DIR}"

if [ -z "${BASE_URL:-}" ] && [ -n "${SERVICE_URL_BUGSINK_8000:-}" ]; then
  # Coolify keeps the container routing port in SERVICE_URL_*
  # (e.g. https://host:8000). Visitors reach the app on 443/80, so the
  # port must be stripped or BugSink builds unreachable DSNs from it.
  base_url="${SERVICE_URL_BUGSINK_8000%/}"
  case "$base_url" in
    *:8000)
      base_url="${base_url%:8000}"
      ;;
    https://*:443)
      base_url="${base_url%:443}"
      ;;
    http://*:80)
      base_url="${base_url%:80}"
      ;;
  esac
  export BASE_URL="${base_url}"
fi

if [ -z "${SECRET_KEY:-}" ] && [ -n "${SERVICE_PASSWORD_BUGSINKSECRET:-}" ]; then
  export SECRET_KEY="${SERVICE_PASSWORD_BUGSINKSECRET}"
fi

if [ -z "${CREATE_SUPERUSER:-}" ] && [ -n "${SERVICE_PASSWORD_BUGSINKADMIN:-}" ]; then
  admin_email="${BUGSINK_ADMIN_EMAIL:-admin@example.com}"
  export CREATE_SUPERUSER="${admin_email}:${SERVICE_PASSWORD_BUGSINKADMIN}"
fi

if [ -z "${DATABASE_URL:-}" ] && [ -n "${SERVICE_PASSWORD_BUGSINKDB:-}" ]; then
  db_user="${POSTGRES_USER:-bugsink}"
  db_name="${POSTGRES_DB:-bugsink}"
  db_host="${POSTGRES_HOST:-postgres}"
  db_port="${POSTGRES_PORT:-5432}"
  export DATABASE_URL="postgresql://${db_user}:${SERVICE_PASSWORD_BUGSINKDB}@${db_host}:${db_port}/${db_name}"
fi

export PORT="${PORT:-8000}"
export BEHIND_HTTPS_PROXY="${BEHIND_HTTPS_PROXY:-true}"
export USE_X_FORWARDED_HOST="${USE_X_FORWARDED_HOST:-true}"

exec "$@"

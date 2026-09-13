#!/bin/sh
set -eu

if [ -z "${KANEO_CLIENT_URL:-}" ] && [ -n "${SERVICE_URL_KANEO_5173:-}" ]; then
  # Coolify keeps the container routing port in SERVICE_URL_*
  # (e.g. https://host:5173). Visitors reach the app on 443/80, so the
  # port must be stripped before upstream derives KANEO_API_URL from it.
  client_url="${SERVICE_URL_KANEO_5173%/}"
  client_url="${client_url%:5173}"
  export KANEO_CLIENT_URL="${client_url}"
fi

if [ -z "${AUTH_SECRET:-}" ] && [ -n "${SERVICE_PASSWORD_KANEOAUTH:-}" ]; then
  export AUTH_SECRET="${SERVICE_PASSWORD_KANEOAUTH}"
fi

if [ -z "${POSTGRES_PASSWORD:-}" ] && [ -n "${SERVICE_PASSWORD_KANEODB:-}" ]; then
  export POSTGRES_PASSWORD="${SERVICE_PASSWORD_KANEODB}"
fi

export POSTGRES_DB="${POSTGRES_DB:-kaneo}"
export POSTGRES_USER="${POSTGRES_USER:-kaneo}"
export POSTGRES_HOST="${POSTGRES_HOST:-postgres}"
export POSTGRES_PORT="${POSTGRES_PORT:-5432}"

if [ -z "${DATABASE_URL:-}" ] && [ -n "${POSTGRES_PASSWORD:-}" ]; then
  export DATABASE_URL="postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@${POSTGRES_HOST}:${POSTGRES_PORT}/${POSTGRES_DB}"
fi

if [ -z "${KANEO_CLIENT_URL:-}" ]; then
  echo "KANEO_CLIENT_URL or SERVICE_URL_KANEO_5173 is required" >&2
  exit 1
fi

if [ -z "${AUTH_SECRET:-}" ]; then
  echo "AUTH_SECRET or SERVICE_PASSWORD_KANEOAUTH is required" >&2
  exit 1
fi

if [ -z "${POSTGRES_PASSWORD:-}" ]; then
  echo "POSTGRES_PASSWORD or SERVICE_PASSWORD_KANEODB is required" >&2
  exit 1
fi

exec "$@"

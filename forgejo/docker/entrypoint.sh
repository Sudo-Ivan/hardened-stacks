#!/bin/sh
set -eu

USER_UID="${USER_UID:-1000}"
USER_GID="${USER_GID:-1000}"
FORGEJO_TRUSTED_PROXIES="${FORGEJO_TRUSTED_PROXIES:-127.0.0.0/8,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16}"

export USER_UID USER_GID

normalize_root_url() {
  url="${1%/}"
  case "$url" in
    *:3000)
      url="${url%:3000}"
      ;;
    https://*:443)
      url="${url%:443}"
      ;;
    http://*:80)
      url="${url%:80}"
      ;;
  esac
  printf '%s/' "$url"
}

root_url="$(normalize_root_url "${FORGEJO_ROOT_URL:-${SERVICE_URL_FORGEJO_3000:-http://127.0.0.1:3000}}")"
ssh_domain="$(printf '%s' "$root_url" | sed -e 's|https\?://||' -e 's|/.*||' -e 's|:.*||')"

export FORGEJO__server__ROOT_URL="$root_url"
export FORGEJO__server__SSH_DOMAIN="${FORGEJO_SSH_DOMAIN:-$ssh_domain}"
export FORGEJO__server__SSH_PORT="${FORGEJO_SSH_PORT:-2222}"
export FORGEJO__server__SSH_LISTEN_PORT="${FORGEJO_SSH_LISTEN_PORT:-2222}"
export FORGEJO__security__REVERSE_PROXY_LIMIT="${FORGEJO_REVERSE_PROXY_LIMIT:-1}"
export FORGEJO__security__REVERSE_PROXY_TRUSTED_PROXIES="$FORGEJO_TRUSTED_PROXIES"

if [ -n "${FORGEJO_APP_NAME:-}" ]; then
  export FORGEJO____APP_NAME="$FORGEJO_APP_NAME"
fi

if [ -n "${FORGEJO_DISABLE_REGISTRATION:-}" ]; then
  export FORGEJO__service__DISABLE_REGISTRATION="$FORGEJO_DISABLE_REGISTRATION"
fi

exec /usr/local/bin/docker-entrypoint.sh "$@"

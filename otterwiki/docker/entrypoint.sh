#!/bin/sh
set -eu

OTTERWIKI_SETTINGS="${OTTERWIKI_SETTINGS:-/app-data/settings.cfg}"
OTTERWIKI_REPOSITORY="${OTTERWIKI_REPOSITORY:-/app-data/repository}"
GUNICORN_BIND="${GUNICORN_BIND:-127.0.0.1:8000}"
GUNICORN_WORKERS="${GUNICORN_WORKERS:-2}"
GUNICORN_TIMEOUT="${GUNICORN_TIMEOUT:-120}"
NGINX_CLIENT_MAX_BODY_SIZE="${NGINX_CLIENT_MAX_BODY_SIZE:-32m}"

export OTTERWIKI_SETTINGS OTTERWIKI_REPOSITORY HOME=/app-data

mkdir -p \
  /app-data \
  "${OTTERWIKI_REPOSITORY}" \
  /tmp/nginx \
  /var/lib/nginx/body \
  /var/lib/nginx/fastcgi \
  /var/lib/nginx/proxy \
  /var/lib/nginx/scgi \
  /var/lib/nginx/uwsgi

init_repository() {
  if [ ! -d "${OTTERWIKI_REPOSITORY}/.git" ]; then
    git init -b main "${OTTERWIKI_REPOSITORY}"
  fi
}

init_settings() {
  if [ -f "${OTTERWIKI_SETTINGS}" ]; then
    return 0
  fi
  secret="$(python3 -c 'import secrets; print(secrets.token_hex(32))')"
  cat > "${OTTERWIKI_SETTINGS}" <<EOF
DEBUG = False
REPOSITORY = '${OTTERWIKI_REPOSITORY}'
SECRET_KEY = '${secret}'
SQLALCHEMY_DATABASE_URI = 'sqlite:////app-data/db.sqlite'
EOF
}

configure_nginx() {
  static_path="$(cat /etc/otterwiki-static-path)"
  sed \
    -e "s|STATIC_PATH_PLACEHOLDER|${static_path}|g" \
    -e "s|client_max_body_size 32m;|client_max_body_size ${NGINX_CLIENT_MAX_BODY_SIZE};|g" \
    /etc/nginx/nginx.conf > /tmp/nginx/nginx.conf
}

start_services() {
  gunicorn \
    --bind "${GUNICORN_BIND}" \
    --workers "${GUNICORN_WORKERS}" \
    --timeout "${GUNICORN_TIMEOUT}" \
    --access-logfile - \
    --error-logfile - \
    --capture-output \
    --chdir /app \
    otterwiki.server:app &
  gunicorn_pid=$!

  nginx -c /tmp/nginx/nginx.conf -g "daemon off;" &
  nginx_pid=$!

  trap 'kill -TERM ${gunicorn_pid} ${nginx_pid} 2>/dev/null; wait' TERM INT

  while kill -0 "${gunicorn_pid}" 2>/dev/null && kill -0 "${nginx_pid}" 2>/dev/null; do
    sleep 1
  done

  echo "A process exited unexpectedly." >&2
  kill -TERM "${gunicorn_pid}" "${nginx_pid}" 2>/dev/null || true
  wait || true
  exit 1
}

case "${1:-serve}" in
  serve)
    init_repository
    init_settings
    configure_nginx
    nginx -t -c /tmp/nginx/nginx.conf
    start_services
    ;;
  *)
    exec "$@"
    ;;
esac

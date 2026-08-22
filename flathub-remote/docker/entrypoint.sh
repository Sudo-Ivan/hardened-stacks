#!/bin/sh
set -eu

FLATHUB_PORT="${FLATHUB_PORT:-8080}"
FLATHUB_REPO_PATH="${FLATHUB_REPO_PATH:-/data/repo}"
FLATHUB_CONFIG_PATH="${FLATHUB_CONFIG_PATH:-/config}"
FLATHUB_SYNC_INTERVAL="${FLATHUB_SYNC_INTERVAL:-86400}"
FLATHUB_INITIAL_SYNC="${FLATHUB_INITIAL_SYNC:-true}"
FLATHUB_REMOTE_NAME="${FLATHUB_REMOTE_NAME:-flathub}"
FLATHUB_REMOTE_TITLE="${FLATHUB_REMOTE_TITLE:-Flathub mirror}"
FLATHUB_REMOTE_HOMEPAGE="${FLATHUB_REMOTE_HOMEPAGE:-https://flathub.org/}"
FLATHUB_REMOTE_COMMENT="${FLATHUB_REMOTE_COMMENT:-Self-hosted Flathub mirror}"
FLATHUB_REMOTE_DESCRIPTION="${FLATHUB_REMOTE_DESCRIPTION:-Mirrored Flatpak applications from Flathub}"
FLATHUB_REMOTE_ICON="${FLATHUB_REMOTE_ICON:-https://dl.flathub.org/repo/logo.svg}"

normalize_public_url() {
    url="${1%/}"
    case "$url" in
        *:8080)
            url="${url%:8080}"
            ;;
        https://*:443)
            url="${url%:443}"
            ;;
        http://*:80)
            url="${url%:80}"
            ;;
    esac
    printf '%s' "$url"
}

public_url="$(normalize_public_url "${FLATHUB_PUBLIC_URL:-${SERVICE_URL_FLATHUB_8080:-http://127.0.0.1:8080}}")"
repo_url="${public_url}/repo/"

write_flatpakrepo() {
    gpg_key="$(awk -F= '/^GPGKey=/ {print $2}' /etc/flathub/upstream.flatpakrepo)"
    mkdir -p "${FLATHUB_REPO_PATH}"
    cat > "${FLATHUB_REPO_PATH}/flathub.flatpakrepo" <<EOF
[Flatpak Repo]
Version=0.1
Title=${FLATHUB_REMOTE_TITLE}
Url=${repo_url}
Homepage=${FLATHUB_REMOTE_HOMEPAGE}
Comment=${FLATHUB_REMOTE_COMMENT}
Description=${FLATHUB_REMOTE_DESCRIPTION}
Icon=${FLATHUB_REMOTE_ICON}
DefaultBranch=stable
GPGKey=${gpg_key}
EOF
}

prepare_runtime() {
    mkdir -p \
        "${FLATHUB_REPO_PATH}" \
        "${FLATHUB_CONFIG_PATH}" \
        /tmp/nginx/client_body \
        /tmp/nginx/proxy \
        /tmp/nginx/fastcgi \
        /tmp/nginx/uwsgi \
        /tmp/nginx/scgi
    write_flatpakrepo
}

run_sync_loop() {
  (
    while true; do
        sleep "${FLATHUB_SYNC_INTERVAL}"
        /usr/local/bin/flathub-sync.sh || echo "sync failed, retrying on next interval"
    done
  ) &
}

case "${1:-serve}" in
    sync)
        prepare_runtime
        exec /usr/local/bin/flathub-sync.sh
        ;;
    serve)
        prepare_runtime
        if [ "${FLATHUB_INITIAL_SYNC}" = "true" ]; then
            /usr/local/bin/flathub-sync.sh || echo "initial sync failed, serving existing content"
        fi
        if [ "${FLATHUB_SYNC_INTERVAL}" -gt 0 ] 2>/dev/null; then
            run_sync_loop
        fi
        exec nginx -g 'daemon off;'
        ;;
    *)
        exec "$@"
        ;;
esac

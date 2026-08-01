#!/bin/sh
set -eu

COPYPARTY_PORT="${COPYPARTY_PORT:-3923}"
COPYPARTY_ADMIN_USER="${COPYPARTY_ADMIN_USER:-admin}"
COPYPARTY_ADMIN_PASSWORD="${COPYPARTY_ADMIN_PASSWORD:?COPYPARTY_ADMIN_PASSWORD is required}"
COPYPARTY_SITE_NAME="${COPYPARTY_SITE_NAME:-HardenedStacks Files}"
COPYPARTY_XFF_SRC="${COPYPARTY_XFF_SRC:-lan}"

export XDG_CONFIG_HOME=/cfg
export PYTHONUNBUFFERED=1

mkdir -p /cfg/hists /w/public /w/private /state /tmp

seed_public_tree() {
  if [ ! -f /w/public/README.txt ]; then
    cat > /w/public/README.txt <<'EOF'
Welcome to the public file library.

Drop files you want to share into this folder (via Manage below if you are an admin).
Visitors can browse and download everything here.

Admin login: open /manage/ in your browser and sign in to upload or organize files.
EOF
  fi

  if [ ! -f /w/private/README.txt ]; then
    cat > /w/private/README.txt <<'EOF'
Private storage. Only visible to admins under /manage/private/
EOF
  fi
}

write_rproxy_config() {
  cat > /cfg/rproxy.conf <<EOF
[global]
  xff-hdr: x-forwarded-for
  xff-src: ${COPYPARTY_XFF_SRC}
  rproxy: 1
  xf-proto: x-forwarded-proto
  xf-host: x-forwarded-host
EOF
}

write_config() {
  if [ -f /cfg/copyparty.conf ]; then
    return 0
  fi

  cat > /cfg/copyparty.conf <<EOF
[global]
  chdir: /w
  name: ${COPYPARTY_SITE_NAME}
  no-crt
  hist: /cfg/hists/
  ansi
  e2dsa
  e2ts
  no-robots
  force-js
  grid
  sort: named
  df: 2
  ver

[accounts]
  ${COPYPARTY_ADMIN_USER}: ${COPYPARTY_ADMIN_PASSWORD}

[/]
  /w/public
  accs:
    r: *
  flags:
    e2ds, e2ts, grid, robots, og, sort: named, dthumb

[/manage]
  /w
  accs:
    rwmda: ${COPYPARTY_ADMIN_USER}
  flags:
    e2ds, grid
EOF
}

case "${1:-serve}" in
  serve)
    seed_public_tree
    write_rproxy_config
    write_config
    exec /venv/bin/python -m copyparty -c /cfg/copyparty.conf -c /cfg/rproxy.conf -p "${COPYPARTY_PORT}"
    ;;
  *)
    exec "$@"
    ;;
esac

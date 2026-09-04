#!/bin/sh
set -eu

mkdir -p /tmp
if touch /data/.write_test 2>/dev/null; then
  rm -f /data/.write_test
  mkdir -p /data/proxy /data/manual-certs /data/admin
  chown -R nonroot:nonroot /data
else
  for d in /data/admin /data/proxy; do
    if [ -d "$d" ] && touch "$d/.write_test" 2>/dev/null; then
      rm -f "$d/.write_test"
      chown -R nonroot:nonroot "$d"
    fi
  done
fi

exec su-exec nonroot:nonroot /ravenguard "$@"

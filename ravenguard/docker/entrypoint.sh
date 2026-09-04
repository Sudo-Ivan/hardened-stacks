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

# Coolify cannot set a custom command. Honor RG_MODE and RG_CONFIG via argv.
# If $1 is already hub|proxy|all, that CLI token wins over RG_MODE.
mode=""
case "${1:-}" in
  hub|proxy|all)
    mode="$1"
    shift
    ;;
esac
case "${RG_MODE:-}" in
  hub|proxy|all)
    mode="$RG_MODE"
    ;;
  "")
    ;;
  *)
    echo "RG_MODE must be all, hub, or proxy (got ${RG_MODE})" >&2
    exit 1
    ;;
esac

config="${RG_CONFIG:-}"
rest=""
skip=0
for a in "$@"; do
  if [ "$skip" -eq 1 ]; then
    skip=0
    if [ -z "$config" ]; then
      config="$a"
    fi
    continue
  fi
  if [ "$a" = "-config" ]; then
    skip=1
    continue
  fi
  rest="$rest $a"
done
if [ -z "$config" ]; then
  config="/config/ravenguard.toml"
fi

# rest only holds prior flag tokens from CMD/compose.
# shellcheck disable=SC2086
if [ -n "$mode" ] && [ "$mode" != "all" ]; then
  exec su-exec nonroot:nonroot /ravenguard "$mode" -config "$config" $rest
fi
# shellcheck disable=SC2086
exec su-exec nonroot:nonroot /ravenguard -config "$config" $rest

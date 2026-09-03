#!/bin/sh
set -eu

DATA_DIR="${DATA_DIR:-/data}"

mkdir -p \
  "${DATA_DIR}/uploads/screenshots" \
  "${DATA_DIR}/uploads/attachments" \
  "${DATA_DIR}/uploads/branding" \
  "${DATA_DIR}/uploads/avatars"

exec "$@"

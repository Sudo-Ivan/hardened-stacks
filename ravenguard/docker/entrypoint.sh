#!/bin/sh
set -eu

mkdir -p /data/admin /tmp
chown -R nonroot:nonroot /data/admin

exec su-exec nonroot:nonroot /ravenguard "$@"

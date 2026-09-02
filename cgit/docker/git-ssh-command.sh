#!/bin/sh
set -eu

log() {
  printf '%s\n' "$1" >&2
}

if [ -z "${SSH_ORIGINAL_COMMAND:-}" ]; then
  log "interactive SSH is disabled (git only)"
  exit 1
fi

# Git always sends: git-<verb> 'path'
# Word-split is intentional for this fixed protocol shape.
# shellcheck disable=SC2086
set -- $SSH_ORIGINAL_COMMAND
op="${1:-}"
repo="${2:-}"

repo="${repo#\'}"
repo="${repo%\'}"
repo="${repo#\"}"
repo="${repo%\"}"

case "$op" in
  git-upload-pack|git-upload-archive|git-receive-pack)
    ;;
  *)
    log "forbidden SSH command"
    exit 128
    ;;
esac

if [ "$op" = "git-receive-pack" ]; then
  case "${CGIT_SSH_ALLOW_PUSH:-false}" in
    true|TRUE|1|yes|YES|on|ON)
      ;;
    *)
      log "SSH push is disabled"
      exit 128
      ;;
  esac
fi

# Normalize /demo.git, demo.git, /repos/demo.git, repos/demo.git
repo="${repo#/}"
case "$repo" in
  '')
    log "invalid repository path"
    exit 128
    ;;
  repos/*)
    name="${repo#repos/}"
    ;;
  *)
    name="$repo"
    ;;
esac

case "$name" in
  ''|*/*|.*|*..*|*$'\n'*|*$'\r'*)
    log "invalid repository path"
    exit 128
    ;;
  *.git)
    ;;
  *)
    name="${name}.git"
    ;;
esac

repo="/repos/${name}"

if [ ! -d "$repo" ]; then
  log "repository not found"
  exit 128
fi

exec "$op" "$repo"

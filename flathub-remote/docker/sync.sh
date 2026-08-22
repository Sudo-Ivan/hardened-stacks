#!/bin/bash
set -euo pipefail

repo="${FLATHUB_REPO_PATH:-/data/repo}"
upstream="${FLATHUB_UPSTREAM_URL:-https://dl.flathub.org/repo/}"
config_dir="${FLATHUB_CONFIG_PATH:-/config}"
collection_id="${FLATHUB_COLLECTION_ID:-org.flathub.Stable}"
remote_title="${FLATHUB_REMOTE_TITLE:-Flathub mirror}"
remote_homepage="${FLATHUB_REMOTE_HOMEPAGE:-https://flathub.org/}"

trim() {
    local value="$1"
    value="${value#"${value%%[![:space:]]*}"}"
    value="${value%"${value##*[![:space:]]}"}"
    printf '%s' "$value"
}

init_repo() {
    if [ -f "${repo}/config" ]; then
        return 0
    fi

    echo "Initializing ostree repository at ${repo}"
    ostree --repo="${repo}" init --mode=archive-z2 --collection-id="${collection_id}"

    ostree --repo="${repo}" remote add --if-not-exists \
        --set=gpg-verify-summary=false \
        --set=gpg-verify=true \
        --gpg-import=/etc/flathub/flathub.gpg \
        flathub "${upstream}"
}

collect_refs() {
    local refs=()
    local line ref

    if [ -n "${FLATHUB_MIRROR_REFS:-}" ]; then
        IFS=',' read -r -a refs <<< "${FLATHUB_MIRROR_REFS}"
        for ref in "${refs[@]}"; do
            ref="$(trim "$ref")"
            [ -n "$ref" ] && printf '%s\n' "$ref"
        done
    fi

    if [ -f "${config_dir}/refs.txt" ]; then
        while IFS= read -r line || [ -n "$line" ]; do
            line="${line%%#*}"
            line="$(trim "$line")"
            [ -n "$line" ] && printf '%s\n' "$line"
        done < "${config_dir}/refs.txt"
    fi
}

pull_refs() {
    local ref count=0

    while IFS= read -r ref; do
        [ -z "$ref" ] && continue
        echo "Mirroring ${ref}"
        ostree --repo="${repo}" pull --mirror flathub "${ref}"
        count=$((count + 1))
    done < <(collect_refs | sort -u)

    if [ "$count" -eq 0 ]; then
        echo "No refs configured. Set FLATHUB_MIRROR_REFS or mount ${config_dir}/refs.txt"
        return 0
    fi

    echo "Mirrored ${count} ref(s)"
}

update_summary() {
    flatpak build-update-repo \
        --collection-id="${collection_id}" \
        --deploy-collection-id \
        --title="${remote_title}" \
        --homepage="${remote_homepage}" \
        --default-branch=stable \
        "${repo}"
}

init_repo
pull_refs
update_summary

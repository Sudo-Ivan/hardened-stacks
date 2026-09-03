#!/bin/sh
set -eu

USER_UID="${USER_UID:-1000}"
USER_GID="${USER_GID:-1000}"
FORGEJO_TRUSTED_PROXIES="${FORGEJO_TRUSTED_PROXIES:-127.0.0.0/8,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16}"
FORGEJO_CUSTOM="${FORGEJO_CUSTOM:-${GITEA_CUSTOM:-/var/lib/gitea/custom}}"
FORGEJO_LOGO_URL="${FORGEJO_LOGO_URL:-https://hardened-stacks.org/static/img/logo.svg}"
FORGEJO_DEFAULT_THEME="${FORGEJO_DEFAULT_THEME:-forgejo-auto}"

export USER_UID USER_GID FORGEJO_CUSTOM

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

asset_extension() {
  url="$1"
  path="${url%%\?*}"
  path="${path%%\#*}"
  case "$path" in
    *.png|*.PNG)
      printf 'png'
      ;;
    *.jpg|*.jpeg|*.JPG|*.JPEG)
      printf 'jpg'
      ;;
    *.ico|*.ICO)
      printf 'ico'
      ;;
    *.webp|*.WEBP)
      printf 'webp'
      ;;
    *)
      printf 'svg'
      ;;
  esac
}

download_asset() {
  url="$1"
  dest="$2"
  tmp="$(mktemp -p /tmp)"
  if curl -fsSL --connect-timeout 10 --max-time 60 -o "$tmp" "$url"; then
    mv "$tmp" "$dest"
    return 0
  fi
  rm -f "$tmp"
  echo "warning: failed to download branding asset from ${url}" >&2
  return 1
}

apply_branding() {
  img_dir="${FORGEJO_CUSTOM}/public/assets/img"
  css_dir="${FORGEJO_CUSTOM}/public/assets/css"
  tmpl_dir="${FORGEJO_CUSTOM}/templates/custom"
  mkdir -p "$img_dir" "$css_dir" "$tmpl_dir"

  if [ -n "${FORGEJO_LOGO_URL}" ] && [ "${FORGEJO_LOGO_URL}" != "none" ]; then
    ext="$(asset_extension "$FORGEJO_LOGO_URL")"
    case "$ext" in
      png)
        download_asset "$FORGEJO_LOGO_URL" "${img_dir}/logo.png" || true
        ;;
      svg)
        download_asset "$FORGEJO_LOGO_URL" "${img_dir}/logo.svg" || true
        ;;
      *)
        download_asset "$FORGEJO_LOGO_URL" "${img_dir}/logo.${ext}" || true
        if [ -f "${img_dir}/logo.${ext}" ]; then
          cp "${img_dir}/logo.${ext}" "${img_dir}/logo.png"
        fi
        ;;
    esac
  fi

  if [ -n "${FORGEJO_FAVICON_URL:-}" ] && [ "${FORGEJO_FAVICON_URL}" != "none" ]; then
    ext="$(asset_extension "$FORGEJO_FAVICON_URL")"
    case "$ext" in
      png)
        download_asset "$FORGEJO_FAVICON_URL" "${img_dir}/favicon.png" || true
        ;;
      ico)
        download_asset "$FORGEJO_FAVICON_URL" "${img_dir}/favicon.ico" || true
        ;;
      *)
        download_asset "$FORGEJO_FAVICON_URL" "${img_dir}/favicon.svg" || true
        ;;
    esac
  elif [ -f "${img_dir}/logo.svg" ]; then
    cp "${img_dir}/logo.svg" "${img_dir}/favicon.svg"
  elif [ -f "${img_dir}/logo.png" ]; then
    cp "${img_dir}/logo.png" "${img_dir}/favicon.png"
  fi

  header_tmpl="${tmpl_dir}/header.tmpl"
  if [ -n "${FORGEJO_CUSTOM_CSS_URL:-}" ] && [ "${FORGEJO_CUSTOM_CSS_URL}" != "none" ]; then
    if download_asset "$FORGEJO_CUSTOM_CSS_URL" "${css_dir}/pmg-custom.css"; then
      cat >"$header_tmpl" <<'EOF'
<link rel="stylesheet" href="{{AppSubUrl}}/assets/css/pmg-custom.css">
EOF
    fi
  elif [ -f "$header_tmpl" ] && grep -q 'pmg-custom.css' "$header_tmpl" 2>/dev/null; then
    rm -f "$header_tmpl" "${css_dir}/pmg-custom.css"
  fi
}

root_url="$(normalize_root_url "${FORGEJO_ROOT_URL:-${SERVICE_URL_FORGEJO_3000:-http://127.0.0.1:3000}}")"
ssh_domain="$(printf '%s' "$root_url" | sed -e 's|https\?://||' -e 's|/.*||' -e 's|:.*||')"

export FORGEJO__server__ROOT_URL="$root_url"
export FORGEJO__server__SSH_DOMAIN="${FORGEJO_SSH_DOMAIN:-$ssh_domain}"
export FORGEJO__server__SSH_PORT="${FORGEJO_SSH_PORT:-2222}"
export FORGEJO__server__SSH_LISTEN_PORT="${FORGEJO_SSH_LISTEN_PORT:-2222}"
export FORGEJO__security__REVERSE_PROXY_LIMIT="${FORGEJO_REVERSE_PROXY_LIMIT:-1}"
export FORGEJO__security__REVERSE_PROXY_TRUSTED_PROXIES="$FORGEJO_TRUSTED_PROXIES"
export FORGEJO__ui__DEFAULT_THEME="$FORGEJO_DEFAULT_THEME"

if [ -n "${FORGEJO_APP_NAME:-}" ]; then
  export FORGEJO____APP_NAME="$FORGEJO_APP_NAME"
fi

if [ -n "${FORGEJO_APP_SLOGAN:-}" ]; then
  export FORGEJO____APP_SLOGAN="$FORGEJO_APP_SLOGAN"
fi

if [ -n "${FORGEJO_THEMES:-}" ]; then
  export FORGEJO__ui__THEMES="$FORGEJO_THEMES"
fi

if [ -n "${FORGEJO_DISABLE_REGISTRATION:-}" ]; then
  export FORGEJO__service__DISABLE_REGISTRATION="$FORGEJO_DISABLE_REGISTRATION"
fi

if [ -n "${FORGEJO_MCAPTCHA_URL:-}" ] && [ -n "${FORGEJO_MCAPTCHA_SITEKEY:-}" ] && [ -n "${FORGEJO_MCAPTCHA_SECRET:-}" ]; then
  export FORGEJO__service__ENABLE_CAPTCHA=true
  export FORGEJO__service__CAPTCHA_TYPE=mcaptcha
  export FORGEJO__service__MCAPTCHA_URL="${FORGEJO_MCAPTCHA_URL}"
  export FORGEJO__service__MCAPTCHA_SITEKEY="${FORGEJO_MCAPTCHA_SITEKEY}"
  export FORGEJO__service__MCAPTCHA_SECRET="${FORGEJO_MCAPTCHA_SECRET}"
  if [ -n "${FORGEJO_REQUIRE_CAPTCHA_FOR_LOGIN:-}" ]; then
    export FORGEJO__service__REQUIRE_CAPTCHA_FOR_LOGIN="$FORGEJO_REQUIRE_CAPTCHA_FOR_LOGIN"
  fi
fi

if [ "${FORGEJO_ENABLE_IMAGE_CAPTCHA:-}" = "true" ]; then
  export FORGEJO__service__ENABLE_CAPTCHA=true
  export FORGEJO__service__CAPTCHA_TYPE=image
fi

apply_branding

exec /usr/local/bin/docker-entrypoint.sh "$@"

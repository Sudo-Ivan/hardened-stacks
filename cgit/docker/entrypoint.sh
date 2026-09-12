#!/bin/sh
set -eu

CGIT_PORT="${CGIT_PORT:-8080}"
CGIT_SSH_PORT="${CGIT_SSH_PORT:-2222}"
CGIT_REPOS_PATH="${CGIT_REPOS_PATH:-/repos}"
CGIT_CONFIG_PATH="${CGIT_CONFIG_PATH:-/cfg}"
CGIT_CACHE_PATH="${CGIT_CACHE_PATH:-/tmp/cgit-cache}"
CGIT_SITE_TITLE="${CGIT_SITE_TITLE:-Hardened Stacks Git}"
CGIT_ROOT_DESC="${CGIT_ROOT_DESC:-Git repositories}"
CGIT_ENABLE_HTTP_CLONE="${CGIT_ENABLE_HTTP_CLONE:-true}"
CGIT_ENABLE_GIT_HTTP="${CGIT_ENABLE_GIT_HTTP:-true}"
CGIT_ENABLE_SSH="${CGIT_ENABLE_SSH:-true}"
CGIT_SSH_ALLOW_PUSH="${CGIT_SSH_ALLOW_PUSH:-false}"
CGIT_FCGI_CHILDREN="${CGIT_FCGI_CHILDREN:-4}"
CGIT_AUTH_USER="${CGIT_AUTH_USER:-}"
CGIT_AUTH_PASSWORD="${CGIT_AUTH_PASSWORD:-}"
CGIT_SSH_AUTHORIZED_KEYS="${CGIT_SSH_AUTHORIZED_KEYS:-}"

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

host_from_url() {
  printf '%s' "$1" | sed -e 's|^[a-zA-Z][a-zA-Z0-9+.-]*://||' -e 's|/.*||' -e 's|\[||' -e 's|\]||' -e 's|:.*||'
}

bool_to_cgit() {
  case "$1" in
    1|true|TRUE|yes|YES|on|ON)
      printf '1'
      ;;
    *)
      printf '0'
      ;;
  esac
}

is_true() {
  case "$1" in
    1|true|TRUE|yes|YES|on|ON)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

public_url="$(normalize_public_url "${CGIT_PUBLIC_URL:-${SERVICE_URL_CGIT_8080:-http://127.0.0.1:8080}}")"
enable_http_clone="$(bool_to_cgit "$CGIT_ENABLE_HTTP_CLONE")"
ssh_host="${CGIT_SSH_HOST:-$(host_from_url "$public_url")}"
ssh_clone_prefix="ssh://git@${ssh_host}:${CGIT_SSH_PORT}"

prepare_runtime() {
  mkdir -p \
    "${CGIT_REPOS_PATH}" \
    "${CGIT_CONFIG_PATH}/ssh" \
    "${CGIT_CACHE_PATH}" \
    /tmp/nginx/client_body \
    /tmp/nginx/proxy \
    /tmp/nginx/fastcgi \
    /tmp/nginx/uwsgi \
    /tmp/nginx/scgi

  write_auth_conf
  write_git_http_conf
  write_cgitrc
  write_nginx_listen
  prepare_ssh
}

write_auth_conf() {
  if [ -n "$CGIT_AUTH_USER" ] && [ -n "$CGIT_AUTH_PASSWORD" ]; then
    hash="$(openssl passwd -apr1 "$CGIT_AUTH_PASSWORD")"
    printf '%s:%s\n' "$CGIT_AUTH_USER" "$hash" > /tmp/htpasswd
    cat > /tmp/nginx-auth.conf <<'EOF'
auth_basic "Git";
auth_basic_user_file /tmp/htpasswd;
EOF
  else
    : > /tmp/nginx-auth.conf
  fi
}

write_git_http_conf() {
  if is_true "$CGIT_ENABLE_GIT_HTTP"; then
    cat > /tmp/nginx-git-http.conf <<EOF
include fastcgi_params;
fastcgi_param SCRIPT_FILENAME /usr/libexec/git-core/git-http-backend;
fastcgi_param GIT_HTTP_EXPORT_ALL "1";
fastcgi_param GIT_PROJECT_ROOT ${CGIT_REPOS_PATH};
fastcgi_param PATH_INFO \$uri;
fastcgi_param QUERY_STRING \$query_string;
fastcgi_param REQUEST_METHOD \$request_method;
fastcgi_param CONTENT_TYPE \$content_type;
fastcgi_param CONTENT_LENGTH \$content_length;
fastcgi_pass unix:/tmp/fcgiwrap.sock;
EOF
  else
    cat > /tmp/nginx-git-http.conf <<'EOF'
return 404;
EOF
  fi
}

write_cgitrc() {
  if [ -f "${CGIT_CONFIG_PATH}/cgitrc" ]; then
    return 0
  fi

  clone_prefix="$public_url"
  if is_true "$CGIT_ENABLE_SSH"; then
    clone_prefix="${public_url} ${ssh_clone_prefix}"
  fi

  cat > "${CGIT_CONFIG_PATH}/cgitrc" <<EOF
css=/cgit.css
logo=/cgit.png
favicon=/favicon.ico
robots=noindex, nofollow
virtual-root=/
root-title=${CGIT_SITE_TITLE}
root-desc=${CGIT_ROOT_DESC}
clone-prefix=${clone_prefix}
enable-http-clone=${enable_http_clone}
enable-index-links=1
enable-index-owner=0
enable-commit-graph=1
enable-log-filecount=1
enable-log-linecount=1
enable-follow-links=1
enable-tree-linenumbers=1
remove-suffix=1
snapshots=tar.gz tar.bz2 zip
max-repo-count=500
cache-root=${CGIT_CACHE_PATH}
cache-size=1000
cache-dynamic-ttl=5
cache-repo-ttl=5
cache-root-ttl=5
scan-path=${CGIT_REPOS_PATH}
EOF
}

write_nginx_listen() {
  if [ "$CGIT_PORT" = "8080" ]; then
    return 0
  fi

  sed "s/listen 8080;/listen ${CGIT_PORT};/" /etc/nginx/nginx.conf > /tmp/nginx.conf
  export NGINX_CONF=/tmp/nginx.conf
}

prepare_ssh() {
  if ! is_true "$CGIT_ENABLE_SSH"; then
    return 0
  fi

  mkdir -p "${CGIT_CONFIG_PATH}/ssh"

  if [ ! -f "${CGIT_CONFIG_PATH}/ssh/ssh_host_ed25519_key" ]; then
    ssh-keygen -t ed25519 -f "${CGIT_CONFIG_PATH}/ssh/ssh_host_ed25519_key" -N "" -q
  fi

  if [ -n "$CGIT_SSH_AUTHORIZED_KEYS" ]; then
    printf '%s\n' "$CGIT_SSH_AUTHORIZED_KEYS" > "${CGIT_CONFIG_PATH}/ssh/authorized_keys"
  elif [ ! -f "${CGIT_CONFIG_PATH}/ssh/authorized_keys" ]; then
    : > "${CGIT_CONFIG_PATH}/ssh/authorized_keys"
  fi
  chmod 600 "${CGIT_CONFIG_PATH}/ssh/authorized_keys" 2>/dev/null || true
  chmod 600 "${CGIT_CONFIG_PATH}/ssh/ssh_host_ed25519_key" 2>/dev/null || true

  cat > /tmp/sshd_config <<EOF
Port ${CGIT_SSH_PORT}
ListenAddress 0.0.0.0
Protocol 2
HostKey ${CGIT_CONFIG_PATH}/ssh/ssh_host_ed25519_key
PidFile /tmp/sshd.pid
AuthorizedKeysFile ${CGIT_CONFIG_PATH}/ssh/authorized_keys
PasswordAuthentication no
KbdInteractiveAuthentication no
ChallengeResponseAuthentication no
PermitRootLogin no
PubkeyAuthentication yes
AllowTcpForwarding no
AllowAgentForwarding no
X11Forwarding no
PermitTunnel no
PermitUserEnvironment no
PrintMotd no
UseDNS no
StrictModes no
LoginGraceTime 30
MaxAuthTries 3
AllowUsers git
SetEnv CGIT_SSH_ALLOW_PUSH=${CGIT_SSH_ALLOW_PUSH}
ForceCommand /usr/local/bin/git-ssh-command
EOF
}

start_sshd() {
  if ! is_true "$CGIT_ENABLE_SSH"; then
    return 0
  fi
  if [ ! -s "${CGIT_CONFIG_PATH}/ssh/authorized_keys" ]; then
    echo "SSH enabled but ${CGIT_CONFIG_PATH}/ssh/authorized_keys is empty (set CGIT_SSH_AUTHORIZED_KEYS or drop a pubkey there)"
    return 0
  fi
  /usr/sbin/sshd -f /tmp/sshd_config -e
}

harden_repo() {
  repo_path="$1"
  git -C "$repo_path" config --local http.receivepack false
  git -C "$repo_path" config --local receive.denyNonFastForwards true
  touch "${repo_path}/git-daemon-export-ok"
}

init_repo() {
  name="${1:?repository name required}"
  case "$name" in
    *.git)
      ;;
    *)
      name="${name}.git"
      ;;
  esac
  case "$name" in
    */*|.*|*..*)
      echo "invalid repository name: $name" >&2
      exit 1
      ;;
  esac

  repo_path="${CGIT_REPOS_PATH}/${name}"
  if [ -e "$repo_path" ]; then
    echo "repository already exists: $name" >&2
    exit 1
  fi

  git -c init.defaultBranch=master init --bare "$repo_path"
  printf '%s' "${2:-$name}" > "${repo_path}/description"
  harden_repo "$repo_path"
  echo "created bare repository ${name}"
}

start_fcgiwrap() {
  rm -f /tmp/fcgiwrap.sock
  spawn-fcgi \
    -s /tmp/fcgiwrap.sock \
    -M 600 \
    -F "${CGIT_FCGI_CHILDREN}" \
    -- /usr/bin/fcgiwrap
}

case "${1:-serve}" in
  init-repo)
    shift
    prepare_runtime
    init_repo "$@"
    ;;
  serve)
    prepare_runtime
    start_fcgiwrap
    start_sshd
    nginx_conf="${NGINX_CONF:-/etc/nginx/nginx.conf}"
    exec nginx -c "$nginx_conf" -g 'daemon off;'
    ;;
  *)
    exec "$@"
    ;;
esac

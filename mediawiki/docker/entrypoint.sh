#!/bin/sh
set -eu

MEDIAWIKI_HOME="${MEDIAWIKI_HOME:-/var/www/html}"
MEDIAWIKI_PERSIST="${MEDIAWIKI_PERSIST:-/persist}"
APACHE_LISTEN_PORT="${APACHE_LISTEN_PORT:-8080}"

DB_HOST="${DB_HOST:-mariadb}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-mediawiki}"
DB_USERNAME="${DB_USERNAME:-mediawiki}"
DB_PASSWORD="${DB_PASSWORD:?DB_PASSWORD is required}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:?DB_ROOT_PASSWORD is required}"

MEDIAWIKI_SITE_NAME="${MEDIAWIKI_SITE_NAME:?MEDIAWIKI_SITE_NAME is required}"
MEDIAWIKI_SITE_SERVER="${MEDIAWIKI_SITE_SERVER:?MEDIAWIKI_SITE_SERVER is required}"
MEDIAWIKI_ADMIN_USER="${MEDIAWIKI_ADMIN_USER:-admin}"
MEDIAWIKI_ADMIN_PASSWORD="${MEDIAWIKI_ADMIN_PASSWORD:?MEDIAWIKI_ADMIN_PASSWORD is required}"
MEDIAWIKI_ADMIN_EMAIL="${MEDIAWIKI_ADMIN_EMAIL:?MEDIAWIKI_ADMIN_EMAIL is required}"

normalize_base_url() {
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

MEDIAWIKI_SITE_SERVER="$(normalize_base_url "${MEDIAWIKI_SITE_SERVER}")"

export MEDIAWIKI_HOME MEDIAWIKI_PERSIST APACHE_LISTEN_PORT
export DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD DB_ROOT_PASSWORD
export MEDIAWIKI_SITE_NAME MEDIAWIKI_SITE_SERVER
export MEDIAWIKI_ADMIN_USER MEDIAWIKI_ADMIN_PASSWORD MEDIAWIKI_ADMIN_EMAIL

mkdir -p "${MEDIAWIKI_PERSIST}/images"

link_persist() {
  if [ ! -L "${MEDIAWIKI_HOME}/LocalSettings.php" ]; then
    rm -f "${MEDIAWIKI_HOME}/LocalSettings.php"
    ln -sfn "${MEDIAWIKI_PERSIST}/LocalSettings.php" "${MEDIAWIKI_HOME}/LocalSettings.php"
  fi

  if [ ! -L "${MEDIAWIKI_HOME}/images" ]; then
    rm -rf "${MEDIAWIKI_HOME}/images"
    ln -sfn "${MEDIAWIKI_PERSIST}/images" "${MEDIAWIKI_HOME}/images"
  fi
}

wait_for_db() {
  echo "Waiting for database ${DB_HOST}:${DB_PORT}..."
  i=0
  while [ "$i" -lt 60 ]; do
    if php -r "
      try {
        new PDO(
          'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT'),
          getenv('DB_USERNAME'),
          getenv('DB_PASSWORD'),
          [PDO::ATTR_TIMEOUT => 3]
        );
        exit(0);
      } catch (Throwable \$e) {
        exit(1);
      }
    "; then
      echo "Database is ready."
      return 0
    fi
    i=$((i + 1))
    sleep 2
  done
  echo "Database did not become ready in time." >&2
  exit 1
}

install_mediawiki() {
  if [ -f "${MEDIAWIKI_PERSIST}/LocalSettings.php" ]; then
    echo "MediaWiki already installed."
    return 0
  fi

  echo "Installing MediaWiki..."
  php maintenance/install.php \
    --dbname="${DB_DATABASE}" \
    --dbpass="${DB_PASSWORD}" \
    --dbserver="${DB_HOST}" \
    --dbport="${DB_PORT}" \
    --dbtype=mysql \
    --dbuser="${DB_USERNAME}" \
    --installdbpass="${DB_ROOT_PASSWORD}" \
    --installdbuser=root \
    --lang=en \
    --pass="${MEDIAWIKI_ADMIN_PASSWORD}" \
    --scriptpath="" \
    --server="${MEDIAWIKI_SITE_SERVER}" \
    "${MEDIAWIKI_SITE_NAME}" \
    "${MEDIAWIKI_ADMIN_USER}"

  php -r "
    \$path = getenv('MEDIAWIKI_PERSIST') . '/LocalSettings.php';
    \$settings = file_get_contents(\$path);
    \$email = getenv('MEDIAWIKI_ADMIN_EMAIL');
    if (strpos(\$settings, '\$wgEmergencyContact') === false) {
      \$settings .= PHP_EOL . '\$wgEmergencyContact = ' . var_export(\$email, true) . ';' . PHP_EOL;
      \$settings .= '\$wgPasswordSender = ' . var_export(\$email, true) . ';' . PHP_EOL;
      file_put_contents(\$path, \$settings);
    }
  "
  echo "MediaWiki installation complete."
}

update_mediawiki() {
  if [ ! -f "${MEDIAWIKI_PERSIST}/LocalSettings.php" ]; then
    return 0
  fi

  php maintenance/update.php --quick
}

start_apache() {
  exec apache2-foreground
}

case "${1:-serve}" in
  serve)
    link_persist
    wait_for_db
    install_mediawiki
    update_mediawiki
    start_apache
    ;;
  *)
    exec "$@"
    ;;
esac

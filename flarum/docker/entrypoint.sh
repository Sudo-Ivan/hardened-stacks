#!/bin/sh
set -eu

FLARUM_HOME="${FLARUM_HOME:-/app}"
cd "${FLARUM_HOME}"

DB_HOST="${DB_HOST:-mariadb}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-flarum}"
DB_USERNAME="${DB_USERNAME:-flarum}"
DB_PASSWORD="${DB_PASSWORD:?DB_PASSWORD is required}"
DB_PREFIX="${DB_PREFIX:-flarum_}"

FLARUM_BASE_URL="${FLARUM_BASE_URL:?FLARUM_BASE_URL is required}"
FLARUM_FORUM_TITLE="${FLARUM_FORUM_TITLE:?FLARUM_FORUM_TITLE is required}"
FLARUM_ADMIN_USERNAME="${FLARUM_ADMIN_USERNAME:-admin}"
FLARUM_ADMIN_PASSWORD="${FLARUM_ADMIN_PASSWORD:?FLARUM_ADMIN_PASSWORD is required}"
FLARUM_ADMIN_EMAIL="${FLARUM_ADMIN_EMAIL:?FLARUM_ADMIN_EMAIL is required}"
FLARUM_DEBUG="${FLARUM_DEBUG:-false}"

PHP_MEMORY_LIMIT="${PHP_MEMORY_LIMIT:-256M}"
PHP_UPLOAD_MAX_SIZE="${PHP_UPLOAD_MAX_SIZE:-16M}"

export FLARUM_HOME
export DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD DB_PREFIX
export FLARUM_BASE_URL FLARUM_FORUM_TITLE FLARUM_ADMIN_USERNAME
export FLARUM_ADMIN_PASSWORD FLARUM_ADMIN_EMAIL FLARUM_DEBUG
export PHP_MEMORY_LIMIT PHP_UPLOAD_MAX_SIZE

mkdir -p \
  /tmp/nginx \
  /var/lib/nginx/tmp/client_body \
  /var/lib/nginx/tmp/proxy \
  /var/lib/nginx/tmp/fastcgi \
  /var/lib/nginx/tmp/uwsgi \
  /var/lib/nginx/tmp/scgi \
  storage/cache \
  storage/formatter \
  storage/less \
  storage/locale \
  storage/logs \
  storage/sessions \
  storage/tmp \
  storage/views \
  public/assets

wait_for_db() {
  echo "Waiting for database ${DB_HOST}:${DB_PORT}..."
  i=0
  while [ "$i" -lt 60 ]; do
    if php -r "
      try {
        new PDO(
          'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
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

write_install_file() {
  install_file="$1"
  INSTALL_FILE="${install_file}" php -r '
    $debug = filter_var(getenv("FLARUM_DEBUG") ?: "false", FILTER_VALIDATE_BOOLEAN);
    $config = [
      "debug" => $debug,
      "baseUrl" => rtrim(getenv("FLARUM_BASE_URL"), "/"),
      "databaseConfiguration" => [
        "driver" => "mysql",
        "host" => getenv("DB_HOST"),
        "port" => (int) getenv("DB_PORT"),
        "database" => getenv("DB_DATABASE"),
        "username" => getenv("DB_USERNAME"),
        "password" => getenv("DB_PASSWORD"),
        "prefix" => getenv("DB_PREFIX") ?: "",
      ],
      "adminUser" => [
        "username" => getenv("FLARUM_ADMIN_USERNAME"),
        "password" => getenv("FLARUM_ADMIN_PASSWORD"),
        "email" => getenv("FLARUM_ADMIN_EMAIL"),
      ],
      "settings" => [
        "forum_title" => getenv("FLARUM_FORUM_TITLE"),
      ],
    ];
    file_put_contents(getenv("INSTALL_FILE"), json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  '
}

sync_config() {
  php -r '
    $path = "/persist/config.php";
    $config = require $path;
    $config["url"] = rtrim(getenv("FLARUM_BASE_URL"), "/");
    $config["debug"] = filter_var(getenv("FLARUM_DEBUG") ?: "false", FILTER_VALIDATE_BOOLEAN);
    if (isset($config["database"])) {
      $config["database"]["host"] = getenv("DB_HOST");
      $config["database"]["port"] = (int) getenv("DB_PORT");
      $config["database"]["database"] = getenv("DB_DATABASE");
      $config["database"]["username"] = getenv("DB_USERNAME");
      $config["database"]["password"] = getenv("DB_PASSWORD");
      $prefix = getenv("DB_PREFIX");
      if ($prefix !== false && $prefix !== null) {
        $config["database"]["prefix"] = $prefix;
      }
    }
    $export = var_export($config, true);
    file_put_contents($path, "<?php return " . $export . ";\n");
  '
}

install_or_migrate() {
  if [ ! -f /persist/config.php ]; then
    echo "Installing Flarum..."
    install_file="$(mktemp /tmp/flarum-install.XXXXXX.json)"
    write_install_file "${install_file}"
    php flarum install --file="${install_file}"
    rm -f "${install_file}"
    echo "Flarum installation complete."
  else
    echo "Updating config and applying migrations..."
    sync_config
    php flarum migrate
    php flarum cache:clear || true
  fi
}

start_services() {
  php-fpm -F &
  php_pid=$!

  nginx -g "daemon off;" &
  nginx_pid=$!

  trap 'kill -TERM ${php_pid} ${nginx_pid} 2>/dev/null; wait' TERM INT

  while kill -0 "${php_pid}" 2>/dev/null && kill -0 "${nginx_pid}" 2>/dev/null; do
    sleep 1
  done

  echo "A process exited unexpectedly." >&2
  kill -TERM "${php_pid}" "${nginx_pid}" 2>/dev/null || true
  wait || true
  exit 1
}

case "${1:-serve}" in
  serve)
    wait_for_db
    install_or_migrate
    start_services
    ;;
  *)
    exec "$@"
    ;;
esac

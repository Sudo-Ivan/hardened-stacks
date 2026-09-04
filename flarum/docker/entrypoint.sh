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

FLARUM_BASE_URL="$(normalize_base_url "${FLARUM_BASE_URL:?FLARUM_BASE_URL is required}")"
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
    install_file="$(mktemp /tmp/flarum-install.XXXXXX)"
    write_install_file "${install_file}"
    php /usr/local/bin/install-flarum.php "${install_file}"
    rm -f "${install_file}"
    echo "Flarum installation complete."
  else
    echo "Updating config and applying migrations..."
    sync_config
  fi

  php flarum extension:enable flarum-suspend 2>/dev/null || true
  php flarum extension:enable flarum-lock 2>/dev/null || true
  php flarum extension:enable hardened-stacks-spam-protection 2>/dev/null || true
  php flarum extension:enable hardened-stacks-delete-users 2>/dev/null || true
  php flarum extension:enable hardened-stacks-altcha 2>/dev/null || true
  php flarum extension:enable hardened-stacks-maintenance 2>/dev/null || true
  php flarum migrate --force 2>/dev/null || php flarum migrate || true
  php flarum cache:clear || true
  scrub_sourcemaps

  ensure_altcha_secret
  ensure_spam_ai_settings
  ensure_maintenance_mode
}

scrub_sourcemaps() {
  echo "Removing public JS source maps..."
  find public/assets -type f -name '*.map' -delete 2>/dev/null || true
  find public/assets -type f -name '*.js' -exec sed -i '/sourceMappingURL/d' {} + 2>/dev/null || true
}

ensure_maintenance_mode() {
  mode="${FLARUM_MAINTENANCE_MODE:-}"
  if [ -z "${mode}" ]; then
    echo "FLARUM_MAINTENANCE_MODE unset. Maintenance stays at admin setting (default off)."
    return 0
  fi

  FLARUM_MAINTENANCE_MODE="${mode}" \
  php -r '
    try {
      require "/app/vendor/autoload.php";
      $site = require "/app/site.php";
      $app = $site->bootApp();
      $settings = $app->getContainer()->make(Flarum\Settings\SettingsRepositoryInterface::class);
      $mode = strtolower(trim((string) getenv("FLARUM_MAINTENANCE_MODE")));
      $normalized = match ($mode) {
        "banner", "notice", "info" => "banner",
        "read_only", "readonly", "read-only" => "read_only",
        "closed", "full", "lockdown", "maintenance" => "closed",
        default => "off",
      };
      $settings->set("hardened-stacks-maintenance.mode", $normalized);
      fwrite(STDOUT, "Maintenance mode set to {$normalized} from FLARUM_MAINTENANCE_MODE.\n");
    } catch (Throwable $e) {
      fwrite(STDOUT, "Maintenance mode will use FLARUM_MAINTENANCE_MODE when Flarum boots.\n");
    }
  ' 2>/dev/null || echo "Maintenance mode will use FLARUM_MAINTENANCE_MODE when Flarum boots."
}

ensure_spam_ai_settings() {
  if [ -z "${SPAM_AI_API_KEY:-}" ]; then
    echo "SPAM_AI_API_KEY is not set. Configure AI spam in Admin or set the env var."
    return 0
  fi

  SPAM_AI_API_KEY="${SPAM_AI_API_KEY}" \
  SPAM_AI_BASE_URL="${SPAM_AI_BASE_URL:-https://openrouter.ai/api/v1}" \
  SPAM_AI_MODEL="${SPAM_AI_MODEL:-openai/gpt-4o-mini}" \
  php -r '
    try {
      require "/app/vendor/autoload.php";
      $site = require "/app/site.php";
      $app = $site->bootApp();
      $settings = $app->getContainer()->make(Flarum\Settings\SettingsRepositoryInterface::class);
      $settings->set("hardened-stacks-spam-protection.enabled", "1");
      if ((string) $settings->get("hardened-stacks-spam-protection.api_key", "") === "") {
        $settings->set("hardened-stacks-spam-protection.api_key", (string) getenv("SPAM_AI_API_KEY"));
      }
      if ((string) $settings->get("hardened-stacks-spam-protection.base_url", "") === "") {
        $settings->set("hardened-stacks-spam-protection.base_url", (string) getenv("SPAM_AI_BASE_URL"));
      }
      if ((string) $settings->get("hardened-stacks-spam-protection.model", "") === "") {
        $settings->set("hardened-stacks-spam-protection.model", (string) getenv("SPAM_AI_MODEL"));
      }
      fwrite(STDOUT, "AI spam protection enabled. Admin settings can change key, base URL, and model.\n");
    } catch (Throwable $e) {
      fwrite(STDOUT, "AI spam protection will use env or admin settings when Flarum boots.\n");
    }
  ' 2>/dev/null || echo "AI spam protection will use env or admin settings when Flarum boots."
}

ensure_altcha_secret() {
  if [ -n "${ALTCHA_HMAC_SECRET:-}" ]; then
    echo "ALTCHA_HMAC_SECRET is set from the environment."
    return 0
  fi

  SECRET="$(php -r 'echo bin2hex(random_bytes(32));')" \
  php -r '
    try {
      require "/app/vendor/autoload.php";
      $site = require "/app/site.php";
      $app = $site->bootApp();
      $settings = $app->getContainer()->make(Flarum\Settings\SettingsRepositoryInterface::class);
      if ((string) $settings->get("hardened-stacks-altcha.hmac_secret", "") === "") {
        $settings->set("hardened-stacks-altcha.hmac_secret", getenv("SECRET"));
        fwrite(STDOUT, "ALTCHA HMAC secret auto-configured.\n");
      } else {
        fwrite(STDOUT, "ALTCHA HMAC secret already configured in settings.\n");
      }
    } catch (Throwable $e) {
      fwrite(STDOUT, "ALTCHA HMAC secret will be auto-created on first challenge request.\n");
    }
  ' 2>/dev/null || echo "ALTCHA HMAC secret will be auto-created on first challenge request."
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

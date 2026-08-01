# syntax=docker/dockerfile:1@sha256:87999aa3d42bdc6bea60565083ee17e86d1f3339802f543c0d03998580f9cb89

ARG PHP_VERSION=8.4
ARG GO_IMAGE=golang:1.26.5-alpine@sha256:0178a641fbb4858c5f1b48e34bdaabe0350a330a1b1149aabd498d0699ff5fb2
ARG PHP_IMAGE=php:8.4-fpm-alpine@sha256:38aca005bd69e4046f8fbf8aa4f700bf5e1474d36edb85953da2b5fe848ed3b4

FROM ${GO_IMAGE} AS backup-cli

WORKDIR /src
COPY flarum-backup-cli/go.mod ./
COPY flarum-backup-cli/*.go ./
ARG VERSION=dev
ARG COMMIT=none
RUN CGO_ENABLED=0 go test -count=1 ./... \
    && CGO_ENABLED=0 go build -trimpath -ldflags="-s -w -X main.version=${VERSION} -X main.commit=${COMMIT}" -o /out/flarum-backup .

FROM ${PHP_IMAGE} AS runtime

ARG FLARUM_VERSION=1.8.17
ARG PHP_VERSION=8.4
ARG FLARUM_UID=10001
ARG FLARUM_GID=10001

ENV FLARUM_HOME=/app \
    PHP_MEMORY_LIMIT=256M \
    PHP_UPLOAD_MAX_SIZE=16M \
    PHP_OPCACHE_MEMORY=128

RUN apk add --no-cache \
      curl \
      freetype \
      icu-libs \
      libjpeg-turbo \
      libpng \
      libwebp \
      libzip \
      mariadb-client \
      nginx \
      tini \
    && apk add --no-cache --virtual .build-deps \
      $PHPIZE_DEPS \
      freetype-dev \
      icu-dev \
      libjpeg-turbo-dev \
      libpng-dev \
      libwebp-dev \
      libzip-dev \
      oniguruma-dev \
    && docker-php-ext-configure gd \
      --with-freetype \
      --with-jpeg \
      --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
      exif \
      gd \
      intl \
      mysqli \
      opcache \
      pdo_mysql \
      zip \
    && apk del .build-deps \
    && rm -rf /tmp/pear /var/cache/apk/*

RUN addgroup -g "${FLARUM_GID}" -S flarum \
    && adduser -u "${FLARUM_UID}" -S -D -H -h "${FLARUM_HOME}" -G flarum -g flarum flarum \
    && mkdir -p \
      /tmp/nginx \
      /var/lib/nginx/tmp \
      /var/lib/nginx/logs \
      /var/log/nginx \
      /var/run/nginx \
      /var/run/php \
      "${FLARUM_HOME}" \
    && ln -sf /dev/stderr /var/lib/nginx/logs/error.log \
    && ln -sf /dev/stdout /var/lib/nginx/logs/access.log \
    && rm -f /etc/nginx/http.d/default.conf \
    && rm -f /usr/local/etc/php-fpm.d/www.conf \
      /usr/local/etc/php-fpm.d/docker.conf \
      /usr/local/etc/php-fpm.d/zz-docker.conf \
      /usr/local/etc/php-fpm.d/www.conf.default

WORKDIR ${FLARUM_HOME}

ARG ARCHIVE_URL="https://github.com/flarum/installation-packages/raw/main/packages/v1.x/v${FLARUM_VERSION}/flarum-v${FLARUM_VERSION}-php${PHP_VERSION}.tar.gz"

RUN curl -fsSL "${ARCHIVE_URL}" -o /tmp/flarum.tar.gz \
    && tar -xzf /tmp/flarum.tar.gz -C "${FLARUM_HOME}" \
    && rm -f /tmp/flarum.tar.gz \
    && mkdir -p \
      /persist \
      storage/cache \
      storage/formatter \
      storage/less \
      storage/locale \
      storage/logs \
      storage/sessions \
      storage/tmp \
      storage/views \
      public/assets \
    && ln -sfn /persist/config.php "${FLARUM_HOME}/config.php" \
    && chown -R flarum:flarum "${FLARUM_HOME}" /persist /var/lib/nginx /var/log/nginx /var/run/nginx /var/run/php /tmp/nginx \
    && chmod -R ug+rwX storage public/assets /persist

COPY flarum/docker/php.ini /usr/local/etc/php/conf.d/zz-flarum.ini
COPY flarum/docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-flarum.conf
COPY flarum/docker/nginx.conf /etc/nginx/nginx.conf
COPY flarum/docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY --from=backup-cli /out/flarum-backup /usr/local/bin/flarum-backup

RUN chmod 0555 /usr/local/bin/entrypoint.sh /usr/local/bin/flarum-backup \
    && chown -R flarum:flarum /etc/nginx /usr/local/etc/php /usr/local/etc/php-fpm.d

USER flarum:flarum

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=5 \
  CMD curl -fsS http://127.0.0.1:8080/ >/dev/null || exit 1

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["serve"]

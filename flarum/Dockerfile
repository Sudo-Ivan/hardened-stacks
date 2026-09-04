# syntax=docker/dockerfile:1@sha256:87999aa3d42bdc6bea60565083ee17e86d1f3339802f543c0d03998580f9cb89

ARG PHP_VERSION=8.4
ARG GO_IMAGE=golang:1.26.5-alpine@sha256:0178a641fbb4858c5f1b48e34bdaabe0350a330a1b1149aabd498d0699ff5fb2
ARG NODE_IMAGE=node:22-alpine@sha256:c610fcdfb1d5b4740dd70c284ed3cb16bb857e0f7166196e36a5501df7a3aa32
ARG PHP_IMAGE=php:8.4-fpm-alpine@sha256:38aca005bd69e4046f8fbf8aa4f700bf5e1474d36edb85953da2b5fe848ed3b4

FROM ${NODE_IMAGE} AS extension-js

WORKDIR /build

COPY flarum-ext/altcha/js ./altcha
COPY flarum-ext/delete-users/js ./delete-users
COPY flarum-ext/spam-protection/js ./spam-protection
COPY flarum-ext/maintenance/js ./maintenance

RUN cd altcha && npm ci && npm run build \
    && cd ../delete-users && npm ci && npm run build \
    && cd ../spam-protection && npm ci && npm run build \
    && cd ../maintenance && npm ci && npm run build \
    && find /build -type f -name '*.map' -delete \
    && find /build -type f -name '*.js' -exec sed -i '/sourceMappingURL/d' {} +

FROM ${GO_IMAGE} AS backup-cli

WORKDIR /src
COPY flarum-backup-cli/go.mod ./
COPY flarum-backup-cli/*.go ./
ARG VERSION=dev
ARG COMMIT=none
RUN CGO_ENABLED=0 go test -count=1 ./... \
    && CGO_ENABLED=0 go build -trimpath -ldflags="-s -w -X main.version=${VERSION} -X main.commit=${COMMIT}" -o /out/flarum-backup .

FROM ${PHP_IMAGE} AS runtime

ARG FLARUM_VERSION=1.8.19
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
COPY flarum/docker/install-flarum.php /usr/local/bin/install-flarum.php
COPY flarum-ext/spam-protection ${FLARUM_HOME}/packages/spam-protection
COPY flarum-ext/delete-users ${FLARUM_HOME}/packages/delete-users
COPY flarum-ext/altcha ${FLARUM_HOME}/packages/altcha
COPY flarum-ext/maintenance ${FLARUM_HOME}/packages/maintenance
COPY --from=extension-js /build/altcha/dist ${FLARUM_HOME}/packages/altcha/js/dist
COPY --from=extension-js /build/delete-users/dist ${FLARUM_HOME}/packages/delete-users/js/dist
COPY --from=extension-js /build/spam-protection/dist ${FLARUM_HOME}/packages/spam-protection/js/dist
COPY --from=extension-js /build/maintenance/dist ${FLARUM_HOME}/packages/maintenance/js/dist
COPY --from=backup-cli /out/flarum-backup /usr/local/bin/flarum-backup

RUN find "${FLARUM_HOME}/packages" -type f \( -name '*.map' -o -name '*.js.map' \) -delete \
    && find "${FLARUM_HOME}/packages" -type f -name '*.js' -exec sed -i '/sourceMappingURL/d' {} +

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && cd "${FLARUM_HOME}" \
    && composer config --no-interaction repositories.spam-protection path packages/spam-protection \
    && composer config --no-interaction repositories.delete-users path packages/delete-users \
    && composer config --no-interaction repositories.altcha path packages/altcha \
    && composer config --no-interaction repositories.maintenance path packages/maintenance \
    && composer require hardened-stacks/spam-protection:@dev hardened-stacks/delete-users:@dev hardened-stacks/altcha:@dev hardened-stacks/maintenance:@dev --no-interaction --no-progress --optimize-autoloader \
    && rm -f /usr/local/bin/composer \
    && chown -R flarum:flarum "${FLARUM_HOME}"

RUN chmod 0555 /usr/local/bin/entrypoint.sh /usr/local/bin/install-flarum.php /usr/local/bin/flarum-backup \
    && chown -R flarum:flarum /etc/nginx /usr/local/etc/php /usr/local/etc/php-fpm.d

USER flarum:flarum

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=5 \
  CMD curl -fsS http://127.0.0.1:8080/ >/dev/null || exit 1

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["serve"]

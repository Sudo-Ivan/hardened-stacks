# syntax=docker/dockerfile:1@sha256:87999aa3d42bdc6bea60565083ee17e86d1f3339802f543c0d03998580f9cb89

ARG MEDIAWIKI_IMAGE=mediawiki:1.44@sha256:6f0d98ed0de83befd57eafae0fd34f87513d36bf4b05dd78b029b964fa1b2b22
ARG MEDIAWIKI_UID=10001
ARG MEDIAWIKI_GID=10001

FROM ${MEDIAWIKI_IMAGE} AS runtime

ARG MEDIAWIKI_UID
ARG MEDIAWIKI_GID

ENV MEDIAWIKI_HOME=/var/www/html \
    MEDIAWIKI_PERSIST=/persist \
    APACHE_LISTEN_PORT=8080

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
      curl \
      tini \
    && rm -rf /var/lib/apt/lists/* \
    && groupmod -g "${MEDIAWIKI_GID}" www-data \
    && usermod -u "${MEDIAWIKI_UID}" -g www-data www-data \
    && mkdir -p /persist/images /persist/cache /tmp/apache \
    && rm -f /var/www/html/LocalSettings.php \
    && rm -rf /var/www/html/images /var/www/html/cache \
    && ln -sfn /persist/LocalSettings.php /var/www/html/LocalSettings.php \
    && ln -sfn /persist/images /var/www/html/images \
    && ln -sfn /persist/cache /var/www/html/cache \
    && sed -i "s/Listen 80/Listen ${APACHE_LISTEN_PORT}/" /etc/apache2/ports.conf \
    && sed -i "s/:80>/:${APACHE_LISTEN_PORT}>/" /etc/apache2/sites-available/000-default.conf \
    && chown -R www-data:www-data /var/www/html /persist /tmp/apache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod 0555 /usr/local/bin/entrypoint.sh \
    && chown www-data:www-data /usr/local/bin/entrypoint.sh

USER www-data:www-data

WORKDIR ${MEDIAWIKI_HOME}

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=180s --retries=5 \
  CMD curl -fsS "http://127.0.0.1:8080/api.php?action=query&meta=siteinfo&format=json" >/dev/null || exit 1

ENTRYPOINT ["/usr/bin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["serve"]

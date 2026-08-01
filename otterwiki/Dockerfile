# syntax=docker/dockerfile:1@sha256:87999aa3d42bdc6bea60565083ee17e86d1f3339802f543c0d03998580f9cb89

ARG PYTHON_IMAGE=python:3.12-slim-bookworm@sha256:d50fb7611f86d04a3b0471b46d7557818d88983fc3136726336b2a4c657aa30b
ARG OTTERWIKI_COMMIT=10322bdef3bdff3a1c0e9557ca74747e946a393f
ARG OTTERWIKI_VERSION=v2.22.1
ARG GUNICORN_VERSION=23.0.0

FROM ${PYTHON_IMAGE} AS builder

ARG OTTERWIKI_COMMIT
ARG GUNICORN_VERSION

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
      build-essential \
      git \
      libjpeg62-turbo-dev \
      libpng-dev \
      libwebp-dev \
      zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

RUN pip install --no-cache-dir --prefix=/install \
      "gunicorn==${GUNICORN_VERSION}" \
      "otterwiki @ git+https://github.com/redimp/otterwiki@${OTTERWIKI_COMMIT}"

FROM ${PYTHON_IMAGE} AS runtime

ARG OTTERWIKI_UID=10001
ARG OTTERWIKI_GID=10001

ENV OTTERWIKI_SETTINGS=/app-data/settings.cfg \
    OTTERWIKI_REPOSITORY=/app-data/repository \
    GUNICORN_BIND=127.0.0.1:8000 \
    GUNICORN_WORKERS=2 \
    GUNICORN_TIMEOUT=120 \
    NGINX_CLIENT_MAX_BODY_SIZE=32m \
  HOME=/app-data

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
      curl \
      git \
      libjpeg62-turbo \
      libpng16-16 \
      libwebp7 \
      nginx \
      tini \
      zlib1g \
    && rm -rf /var/lib/apt/lists/* \
    && groupadd -g "${OTTERWIKI_GID}" otterwiki \
    && useradd -u "${OTTERWIKI_UID}" -g otterwiki -d /app -s /usr/sbin/nologin -M otterwiki \
    && mkdir -p \
      /app \
      /app-data \
      /tmp/nginx \
      /var/lib/nginx/body \
      /var/lib/nginx/fastcgi \
      /var/lib/nginx/proxy \
      /var/lib/nginx/scgi \
      /var/lib/nginx/uwsgi \
    && ln -sf /dev/stderr /var/log/nginx/error.log \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && rm -f /etc/nginx/sites-enabled/default

COPY --from=builder /install /usr/local

RUN STATIC_PATH="$(python3 -c 'import otterwiki, pathlib; print(pathlib.Path(otterwiki.__file__).parent / "static")')" \
    && echo "${STATIC_PATH}" > /etc/otterwiki-static-path \
    && chown -R otterwiki:otterwiki /app /app-data /tmp/nginx /var/lib/nginx /var/log/nginx /etc/nginx /etc/otterwiki-static-path

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod 0555 /usr/local/bin/entrypoint.sh \
    && chown -R otterwiki:otterwiki /etc/nginx /usr/local/bin/entrypoint.sh

USER otterwiki:otterwiki

WORKDIR /app

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=5 \
  CMD curl -A "docker-healthcheck" -fsS http://127.0.0.1:8080/-/healthz || exit 1

ENTRYPOINT ["/usr/bin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["serve"]

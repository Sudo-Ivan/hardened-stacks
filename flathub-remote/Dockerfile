# syntax=docker/dockerfile:1@sha256:ecfaec9ed6d810b56388c508f4121597bfbba70d41a6dfeee4d8cad5f295fc32

ARG ALPINE_IMAGE=alpine:3.24@sha256:294b683cb724975bec92580e1e685676bd4b50bda910ddb8c51d4cabeaec77e6

FROM ${ALPINE_IMAGE}

ARG FLATHUB_UID=10001
ARG FLATHUB_GID=10001

ENV FLATHUB_PORT=8080 \
    FLATHUB_REPO_PATH=/data/repo \
    FLATHUB_CONFIG_PATH=/config \
    FLATHUB_UPSTREAM_URL=https://dl.flathub.org/repo/ \
    FLATHUB_COLLECTION_ID=org.flathub.Stable \
    FLATHUB_SYNC_INTERVAL=86400 \
    FLATHUB_INITIAL_SYNC=true \
    FLATHUB_REMOTE_NAME=flathub \
    FLATHUB_REMOTE_TITLE="Flathub mirror" \
    TZ=UTC

RUN apk add --no-cache \
      bash \
      flatpak \
      nginx \
      ostree \
      tini \
      tzdata \
      wget \
    && addgroup -g "${FLATHUB_GID}" flathub \
    && adduser -u "${FLATHUB_UID}" -G flathub -D -H -s /sbin/nologin flathub \
    && mkdir -p /data/repo /config /etc/flathub /var/cache/nginx /var/log/nginx \
    && chown -R flathub:flathub /data /config /var/cache/nginx /var/log/nginx

COPY docker/flathub.gpg /etc/flathub/flathub.gpg
COPY docker/upstream.flathub.flatpakrepo /etc/flathub/upstream.flatpakrepo
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/sync.sh /usr/local/bin/flathub-sync.sh
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod 0555 /usr/local/bin/entrypoint.sh /usr/local/bin/flathub-sync.sh \
    && chown flathub:flathub /usr/local/bin/entrypoint.sh /usr/local/bin/flathub-sync.sh

USER flathub:flathub

WORKDIR /data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=120s --retries=5 \
  CMD wget --spider -q "http://127.0.0.1:8080/health" || exit 1

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["serve"]

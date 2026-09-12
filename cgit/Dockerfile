# syntax=docker/dockerfile:1@sha256:87999aa3d42bdc6bea60565083ee17e86d1f3339802f543c0d03998580f9cb89

ARG ALPINE_IMAGE=alpine:3.22@sha256:14358309a308569c32bdc37e2e0e9694be33a9d99e68afb0f5ff33cc1f695dce

FROM ${ALPINE_IMAGE}

ARG CGIT_UID=10001
ARG CGIT_GID=10001

ENV CGIT_PORT=8080 \
    CGIT_SSH_PORT=2222 \
    CGIT_REPOS_PATH=/repos \
    CGIT_CONFIG_PATH=/cfg \
    CGIT_CACHE_PATH=/tmp/cgit-cache \
    CGIT_SITE_TITLE="Hardened Stacks Git" \
    CGIT_ROOT_DESC="Git repositories" \
    CGIT_ENABLE_HTTP_CLONE=true \
    CGIT_ENABLE_GIT_HTTP=true \
    CGIT_ENABLE_SSH=true \
    CGIT_SSH_ALLOW_PUSH=false \
    TZ=UTC

RUN apk add --no-cache \
      cgit \
      fcgiwrap \
      git \
      git-daemon \
      nginx \
      openssh-keygen \
      openssh-server \
      openssl \
      spawn-fcgi \
      tini \
      tzdata \
      wget \
    && addgroup -g "${CGIT_GID}" git \
    && adduser -u "${CGIT_UID}" -G git -D -h /cfg -s /bin/sh git \
    && mkdir -p /repos /cfg/ssh /var/cache/nginx /var/log/nginx /var/lib/nginx/logs /var/lib/nginx/tmp \
    && ln -sf /dev/stderr /var/lib/nginx/logs/error.log \
    && chown -R git:git /repos /cfg /var/cache/nginx /var/log/nginx /var/lib/nginx

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/git-ssh-command.sh /usr/local/bin/git-ssh-command

RUN chmod 0555 /usr/local/bin/entrypoint.sh /usr/local/bin/git-ssh-command \
    && chown git:git /usr/local/bin/entrypoint.sh /usr/local/bin/git-ssh-command /etc/nginx/nginx.conf

USER git:git

WORKDIR /repos

EXPOSE 8080 2222

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=5 \
  CMD wget --spider -q "http://127.0.0.1:8080/health" || exit 1

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["serve"]

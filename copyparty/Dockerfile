# syntax=docker/dockerfile:1@sha256:ecfaec9ed6d810b56388c508f4121597bfbba70d41a6dfeee4d8cad5f295fc32

ARG ALPINE_IMAGE=alpine:3.24@sha256:294b683cb724975bec92580e1e685676bd4b50bda910ddb8c51d4cabeaec77e6
ARG COPYPARTY_COMMIT=5ccc999f3975830c3bb308ecb1781ddf84b88db4
ARG COPYPARTY_VERSION=v1.20.24

FROM ${ALPINE_IMAGE} AS builder

ARG COPYPARTY_COMMIT

RUN apk add --no-cache \
      build-base \
      curl \
      git \
      libffi-dev \
      libjpeg-turbo-dev \
      openssl-dev \
      python3 \
      py3-pip \
      py3-virtualenv \
      zlib-dev

WORKDIR /src

RUN git init . \
    && git remote add origin https://github.com/9001/copyparty.git \
    && git fetch --depth 1 origin "${COPYPARTY_COMMIT}" \
    && git checkout FETCH_HEAD

ARG COPYPARTY_RELEASE_VERSION=1.20.24

RUN curl -fsSL "https://github.com/9001/copyparty/releases/download/v${COPYPARTY_RELEASE_VERSION}/copyparty-${COPYPARTY_RELEASE_VERSION}.tar.gz" \
      -o /tmp/copyparty-release.tar.gz \
    && rm -rf copyparty/web/deps \
    && tar -xzf /tmp/copyparty-release.tar.gz \
      --strip-components=3 \
      -C copyparty/web \
      "copyparty-${COPYPARTY_RELEASE_VERSION}/copyparty/web/deps" \
    && rm -f /tmp/copyparty-release.tar.gz \
    && test -f copyparty/web/deps/sha512.hw.js.gz \
    && test -f copyparty/web/deps/prismd.css.gz

RUN python3 -m venv /venv \
    && /venv/bin/pip install --no-cache-dir --upgrade pip \
    && /venv/bin/pip install --no-cache-dir ".[all]"

FROM ${ALPINE_IMAGE} AS runtime

ARG COPYPARTY_UID=10001
ARG COPYPARTY_GID=10001

ENV XDG_CONFIG_HOME=/cfg \
    PYTHONUNBUFFERED=1 \
    COPYPARTY_PORT=3923 \
    PATH="/venv/bin:$PATH"

RUN apk add --no-cache \
      ffmpeg \
      python3 \
      tini \
      tzdata \
      wget \
    && addgroup -g "${COPYPARTY_GID}" copyparty \
    && adduser -u "${COPYPARTY_UID}" -G copyparty -D -H -s /sbin/nologin copyparty \
    && mkdir -p /cfg /w /state /cfg/hists \
    && chown -R copyparty:copyparty /cfg /w /state

COPY --from=builder /venv /venv

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod 0555 /usr/local/bin/entrypoint.sh \
    && chown copyparty:copyparty /usr/local/bin/entrypoint.sh

USER copyparty:copyparty

WORKDIR /state

EXPOSE 3923

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=5 \
  CMD wget --spider -q "http://127.0.0.1:3923/?reset=/._" || exit 1

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["serve"]

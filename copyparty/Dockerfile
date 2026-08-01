# syntax=docker/dockerfile:1@sha256:87999aa3d42bdc6bea60565083ee17e86d1f3339802f543c0d03998580f9cb89

ARG ALPINE_IMAGE=alpine:3.22@sha256:14358309a308569c32bdc37e2e0e9694be33a9d99e68afb0f5ff33cc1f695dce
ARG COPYPARTY_COMMIT=ac4be555f6cceacbbbc6d50e681a64612da90711
ARG COPYPARTY_VERSION=v1.20.19

FROM ${ALPINE_IMAGE} AS builder

ARG COPYPARTY_COMMIT

RUN apk add --no-cache \
      build-base \
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
COPY docker/pmg-browser.css /usr/share/copyparty/pmg-browser.css

RUN chmod 0555 /usr/local/bin/entrypoint.sh \
    && chmod 0444 /usr/share/copyparty/pmg-browser.css \
    && chown copyparty:copyparty /usr/local/bin/entrypoint.sh /usr/share/copyparty/pmg-browser.css

USER copyparty:copyparty

WORKDIR /state

EXPOSE 3923

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=5 \
  CMD wget --spider -q "http://127.0.0.1:3923/?reset=/._" || exit 1

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["serve"]

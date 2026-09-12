# syntax=docker/dockerfile:1

# Build RavenGuard from upstream source.
# https://github.com/Quad4-Software/ravenguard
# Fleet modes: all (default), hub, proxy — https://ravenguard.quad4.io/docs/intro
# Admin UI is server-rendered Go templates (no Node build).
ARG RAVENGUARD_REF=1c2ddb11391fac8f09048ddef8a4fcfe386179b8

FROM alpine:3.22 AS src
ARG RAVENGUARD_REF
RUN apk add --no-cache git ca-certificates \
 && git clone https://github.com/Quad4-Software/ravenguard.git /src \
 && cd /src \
 && git checkout "${RAVENGUARD_REF}"

FROM golang:1.26.6-bookworm AS build
WORKDIR /src
COPY --from=src /src/go.mod /src/go.sum ./
RUN go mod download
COPY --from=src /src/ ./
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" -o /out/ravenguard ./cmd/ravenguard

FROM alpine:3.22
RUN apk add --no-cache ca-certificates tini su-exec \
 && adduser -D -u 65532 -H nonroot \
 && mkdir -p /config /blocklists /data/admin /data/proxy /tmp \
 && chown -R nonroot:nonroot /data/admin /data/proxy
COPY --from=build /out/ravenguard /ravenguard
COPY --chmod=0555 docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/seccomp-ravenguard.json /etc/ravenguard/seccomp.json
COPY configs/flarum.toml /config/ravenguard.toml
COPY configs/hub.toml /config/hub.toml
COPY blocklists /blocklists
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=5 \
  CMD wget -q -O /dev/null http://127.0.0.1:8080/healthz || exit 1
ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["-config", "/config/ravenguard.toml"]

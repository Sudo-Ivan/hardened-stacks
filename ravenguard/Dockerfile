# syntax=docker/dockerfile:1

# Build RavenGuard from upstream source.
# https://github.com/Quad4-Software/ravenguard
ARG RAVENGUARD_REF=e338903fa97ee9b212745384c9b6322b167ba573

FROM alpine:3.22 AS src
ARG RAVENGUARD_REF
RUN apk add --no-cache git ca-certificates \
 && git clone https://github.com/Quad4-Software/ravenguard.git /src \
 && cd /src \
 && git checkout "${RAVENGUARD_REF}"

FROM node:22-bookworm AS admin
WORKDIR /src
RUN corepack enable && corepack prepare pnpm@11.24.0 --activate
COPY --from=src /src/package.json /src/pnpm-lock.yaml /src/pnpm-workspace.yaml ./
COPY --from=src /src/packages/admin/package.json packages/admin/
RUN pnpm install --frozen-lockfile --filter @quad4/ravenguard-admin...
COPY --from=src /src/packages/admin packages/admin
COPY --from=src /src/Makefile ./
RUN pnpm admin:build \
 && rm -rf internal/admin/ui/dist \
 && mkdir -p internal/admin/ui/dist \
 && cp -a packages/admin/build/. internal/admin/ui/dist/ \
 && touch internal/admin/ui/dist/.gitkeep

FROM golang:1.26.6-bookworm AS build
WORKDIR /src
COPY --from=src /src/go.mod /src/go.sum ./
RUN go mod download
COPY --from=src /src/ ./
COPY --from=admin /src/internal/admin/ui/dist/ ./internal/admin/ui/dist/
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" -o /out/ravenguard ./cmd/ravenguard

FROM alpine:3.22
RUN apk add --no-cache ca-certificates tini su-exec \
 && adduser -D -u 65532 -H nonroot \
 && mkdir -p /config /blocklists /data/admin /tmp \
 && chown -R nonroot:nonroot /data/admin
COPY --from=build /out/ravenguard /ravenguard
COPY --chmod=0555 docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/seccomp-ravenguard.json /etc/ravenguard/seccomp.json
COPY configs/flarum.toml /config/ravenguard.toml
COPY blocklists /blocklists
EXPOSE 8080 9090
ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["-config", "/config/ravenguard.toml"]

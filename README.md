# Hardened Stacks

Docker stacks for [Coolify](https://coolify.io/). Each service has a local compose file and a Coolify compose file. Images publish to `ghcr.io/sudo-ivan/hardened-stacks`.

Images are digest-pinned where possible, scanned with Trivy, signed with Cosign keyless, and labeled with OCI metadata.

## Quick reference

| Service | Folder | Coolify port | Image |
|---------|--------|--------------|-------|
| Flarum | `flarum/` | `ravenguard:8080` (forum), `ravenguard-hub:8080` (WAF admin) | `ghcr.io/sudo-ivan/hardened-stacks/flarum` + `.../ravenguard` |
| MediaWiki | `mediawiki/` | 8080 | `ghcr.io/sudo-ivan/hardened-stacks/mediawiki` |
| Copyparty | `copyparty/` | 3923 | `ghcr.io/sudo-ivan/hardened-stacks/copyparty` |
| Forgejo | `forgejo/` | 3000 | `ghcr.io/sudo-ivan/hardened-stacks/forgejo` |
| cgit | `cgit/` | 8080 | `ghcr.io/sudo-ivan/hardened-stacks/cgit` |
| BugPin | `bugpin/` | 7300 | `ghcr.io/sudo-ivan/hardened-stacks/bugpin` |
| Bugsink | `bugsink/` | 8000 | `ghcr.io/sudo-ivan/hardened-stacks/bugsink` |
| Kaneo | `kaneo/` | 5173 | `ghcr.io/sudo-ivan/hardened-stacks/kaneo` |
| OneUptime | `oneuptime/` | `ingress:7849` | upstream `oneuptime/*` (digest-pinned) |
| SigNoz | `signoz/` | `signoz:8080`, `otel-collector:4317/4318` (OTLP) | upstream `signoz/*` (digest-pinned) |
| Zitadel | `zitadel/` | `zitadel-api:8080` + `zitadel-login:3000` (`/ui/v2/login`) | upstream `ghcr.io/zitadel/*` (digest-pinned) |
| ntfy | `ntfy/` | 8080 | upstream `binwiederhier/ntfy` (digest-pinned) |
| LiveKit | `livekit/` | `livekit:7880` (signal) + published `7881` TCP, `7882`/udp (media) | upstream `livekit/livekit-server` (digest-pinned) |
| Pocket ID | `pocketid/` | `pocket-id:1411` | upstream `ghcr.io/pocket-id/pocket-id` (distroless, digest-pinned) |
| Verdaccio | `verdaccio/` | `verdaccio:4873` | upstream `verdaccio/verdaccio` (digest-pinned) |
| PrivateBin | `privatebin/` | `privatebin:8080` | upstream `privatebin/nginx-fpm-alpine` (digest-pinned) |
| RavenGuard | `ravenguard/` | built for Flarum (and standalone smoke) | `ghcr.io/sudo-ivan/hardened-stacks/ravenguard` |

Point your Coolify domain at the service port above. Coolify terminates HTTPS on the public URL.

Coolify domain entries look like `https://app.example.com:8080` (the `:8080` is only the *container* port for Traefik). Generated `SERVICE_FQDN_*` / `SERVICE_URL_*` values often still include that port. Never bake those into browser-facing URLs unless an entrypoint strips the routing port (Flarum, Forgejo, MediaWiki, cgit, Bugsink, Kaneo, flathub-remote do). Stacks without a stripper require an explicit public URL env (ntfy, Verdaccio, PrivateBin, Pocket ID, Zitadel, OneUptime `HOST`).

---

## Flarum

Rootless forum image with bundled extensions under `flarum-ext/`. Coolify traffic goes through [RavenGuard](https://github.com/Quad4-Software/ravenguard) in [fleet mode](https://ravenguard.quad4.io/docs/intro) (WAF edge + separate hub):

```text
Client -> Coolify TLS -> ravenguard :8080      -> flarum:8080
Client -> Coolify TLS -> ravenguard-hub :8080  (admin SPA)
```

Day one the edge runs with `RG_MODE=all` and admin disabled so the forum works without enrollment. The hub is a separate Coolify service on port `8080` only (avoids the dual-port warning). After login, enroll the edge from Proxies UI, then set on the **ravenguard** service:

```bash
RG_MODE=proxy
RG_AGENT_HUB_URL=http://ravenguard-hub:8080
RG_AGENT_TOKEN=...
RG_AGENT_HUB_PUBKEY=...
RG_AGENT_NAME=edge-1
```

Redeploy. No custom Docker command is required (`RG_MODE` / `RG_CONFIG` are enough).

### First deploy

Required:

```bash
FLARUM_FORUM_TITLE=Forum
FLARUM_ADMIN_EMAIL=admin@example.com
```

Attach Coolify domains like this (each service exposes only `8080`):

| Domain | Service port |
|--------|--------------|
| Forum (`https://forum.example.com`) | `ravenguard:8080` |
| Guard admin (`https://waf.example.com`) | `ravenguard-hub:8080` |

Do **not** attach domains to `flarum`. Coolify sets `SERVICE_URL_RAVENGUARD_8080` (forum) and `SERVICE_URL_RAVENGUARD_HUB_8080` (hub). Flarum uses:

```bash
FLARUM_BASE_URL=${FLARUM_BASE_URL:-$SERVICE_URL_RAVENGUARD_8080}
```

Override `FLARUM_BASE_URL` only if you need a fixed origin. Do not use Coolify `*.sslip.io` URLs.

Coolify provides:

- `SERVICE_PASSWORD_FLARUMADMIN`
- `SERVICE_PASSWORD_FLARUMDB`
- `SERVICE_PASSWORD_FLARUMROOT`
- `SERVICE_PASSWORD_RAVENGUARD` (challenge HMAC, min 16 chars)
- `SERVICE_PASSWORD_RAVENGUARDADMIN` (hub bootstrap password)

Optional:

```bash
FLARUM_BASE_URL=https://forum.example.com
ALTCHA_HMAC_SECRET=   # auto-generated if empty
SPAM_AI_API_KEY=
SPAM_AI_BASE_URL=https://openrouter.ai/api/v1
SPAM_AI_MODEL=openai/gpt-4o-mini
FLARUM_MAINTENANCE_MODE=off   # off | banner | read_only | closed
RG_UI_BRAND=Forum
RG_UI_STATUS_TEXT=Checking your browser before accessing Forum.
RG_CHALLENGE_ENABLED=true
RG_TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12,192.168.0.0/16
```

The entrypoint rewrites Flarum `config.php` `url` from `FLARUM_BASE_URL` on every start.

RavenGuard keeps its raven logo; challenge branding text comes from `RG_UI_BRAND` (default Forum). Change the hub admin password after first login.

### Bundled extensions

**AI spam protection** (`hardened-stacks-spam-protection`)

OpenAI-compatible classifier (OpenRouter by default). Monitors new posts and registrations, then can hide posts, hide or lock discussions, and suspend users.

Configure under **Admin → Extensions → AI Spam Protection**, or seed from env on first boot:

```bash
SPAM_AI_API_KEY=sk-or-...
SPAM_AI_BASE_URL=https://openrouter.ai/api/v1
SPAM_AI_MODEL=openai/gpt-4o-mini
```

Admin settings for API key, base URL, and model override the environment once saved.

**ALTCHA** (`hardened-stacks-altcha`)

Self-hosted proof-of-work for registration (optional login / password reset). HMAC secret is auto-generated on first boot. Optional pin:

```bash
ALTCHA_HMAC_SECRET=$(openssl rand -hex 32)
```

**Maintenance** (`hardened-stacks-maintenance`)

| Mode | Effect |
|------|--------|
| `off` | Normal forum |
| `banner` | Notice banner only |
| `read_only` | Browse OK, posting blocked for non-admins |
| `closed` | Maintenance page for everyone except admins |

```bash
FLARUM_MAINTENANCE_MODE=closed
```

Or set the mode under **Admin → Extensions → Maintenance**.

**Delete users** (`hardened-stacks-delete-users`)

Admins can permanently delete accounts from the admin users page (row action and bulk select) or the user editor. Content is soft-deleted first.

### Backup CLI

Bundled as `flarum-backup` in the image. Source is in `flarum-backup-cli/`.

```bash
docker compose exec flarum flarum-backup export -o /backups/forum.tar.gz
docker compose exec -T flarum flarum-backup restore -i /backups/forum.tar.gz --force
```

---

## MediaWiki

Official MediaWiki image, rootless wrapper, MariaDB backend.

### First deploy

```bash
MEDIAWIKI_SITE_NAME=HardenedStacks Wiki
MEDIAWIKI_ADMIN_EMAIL=admin@example.com
MEDIAWIKI_ADMIN_PASSWORD=...   # or Coolify SERVICE_PASSWORD_MEDIAWIKIADMIN
```

Coolify also provides `SERVICE_PASSWORD_MEDIAWIKIDB` and `SERVICE_PASSWORD_MEDIAWIKIROOT`.

Optional logo:

```bash
MEDIAWIKI_LOGO_URL=none
```

`LocalSettings.php` and uploads live in the `mediawiki_persist` volume. Public URL `:8080` is stripped automatically. Override with `MEDIAWIKI_SITE_SERVER` if needed.

---

## Copyparty

Rootless [copyparty](https://github.com/9001/copyparty) file browser.

### First deploy

```bash
COPYPARTY_ADMIN_PASSWORD=...   # or Coolify SERVICE_PASSWORD_COPYPARTYADMIN
```

Public files go in the `public` folder at the site root. Uploads need admin login at `/manage/`.

Optional:

```bash
COPYPARTY_SITE_NAME=HardenedStacks Files
```

To refresh config defaults, delete `copyparty.conf` from the config volume and redeploy.

---

## Forgejo

Official rootless [Forgejo](https://forgejo.org/docs/latest/) image with a small proxy/branding wrapper.

### First deploy

```bash
FORGEJO_DB_PASSWORD=...   # or Coolify SERVICE_PASSWORD_FORGEJODB
```

Finish setup in the web installer.

### Branding

```bash
FORGEJO_APP_NAME=Hardened Stacks Git
FORGEJO_APP_SLOGAN=optional subtitle
FORGEJO_LOGO_URL=none
FORGEJO_FAVICON_URL=
FORGEJO_DEFAULT_THEME=forgejo-auto
FORGEJO_THEMES=forgejo-auto,forgejo-light,forgejo-dark
FORGEJO_CUSTOM_CSS_URL=
```

Set `FORGEJO_LOGO_URL=none` for stock Forgejo branding. Logo and CSS are fetched into the data volume on each start.

### Git SSH

Expose port `2222` if you want `git@` clone URLs.

### CAPTCHA

Forgejo does not support ALTCHA. Options:

```bash
# Built-in image captcha
FORGEJO_ENABLE_IMAGE_CAPTCHA=true
```

Or mCaptcha (proof-of-work):

```bash
docker compose -f docker-compose.yml -f docker-compose.captcha.yml up -d
```

Then set `FORGEJO_MCAPTCHA_URL`, `FORGEJO_MCAPTCHA_SITEKEY`, and `FORGEJO_MCAPTCHA_SECRET`.

Forgejo and BugPin run as UID `1000`. The other stacks use `10001`.

---

## cgit

Rootless [cgit](https://git.zx2c4.com/cgit/) with nginx, fcgiwrap, git smart HTTP, and optional Git SSH.

### First deploy

Point Coolify at port `8080`. Publish TCP `2222` for Git SSH. Bare repos live under `/repos` in the `cgit_repos` volume.

```bash
docker compose exec cgit entrypoint.sh init-repo myproject "My project"
git clone https://your-host/myproject.git
```

### Git SSH

```bash
CGIT_SSH_AUTHORIZED_KEYS="ssh-ed25519 AAAA... user@host"
# or write keys to cfg volume: ssh/authorized_keys
CGIT_SSH_HOST=git.example.com
CGIT_SSH_ALLOW_PUSH=false
```

```bash
git clone ssh://git@your-host:2222/myproject.git
```

Host keys are generated once into the cfg volume.

### Access

Browse and clone/fetch are enabled. HTTP push is off. Optional site-wide basic auth:

```bash
CGIT_AUTH_USER=reader
CGIT_AUTH_PASSWORD=...
```

Optional index text:

```bash
CGIT_SITE_TITLE=Hardened Stacks Git
CGIT_ROOT_DESC=Public repositories
```

Delete `cgitrc` from the cfg volume and redeploy to regenerate defaults.

---

## BugPin

Rootless wrapper around [BugPin](https://github.com/aranticlabs/bugpin) with a read-only rootfs.

### First deploy

1. Log in with `admin@example.com` / `changeme123`
2. Change the password under **Users**
3. Set the public App URL under **Settings → General**
4. Enable **Enforce HTTPS** under **Security** once the proxy sends `X-Forwarded-Proto`

Create a project, copy the API key, and embed the widget from your BugPin host. Restrict allowed domains per project for production.

Data (SQLite, session secret, screenshots, branding) lives in `bugpin_data` under `/data`.

BugPin runs as UID `1000` (same as Forgejo).

---

## Bugsink

Rootless wrapper around [Bugsink](https://github.com/bugsink/bugsink) (Sentry-SDK compatible error tracking) with PostgreSQL 17 and a read-only rootfs.

### First deploy

Point Coolify at port `8000`. Coolify provides:

- `SERVICE_PASSWORD_BUGSINKSECRET` (Django `SECRET_KEY`, long random)
- `SERVICE_PASSWORD_BUGSINKADMIN` (bootstrap admin password)
- `SERVICE_PASSWORD_BUGSINKDB`

Optional:

```bash
BUGSINK_ADMIN_EMAIL=admin@example.com
BUGSINK_BASE_URL=https://errors.example.com   # else SERVICE_URL_BUGSINK_8000 (container port stripped)
```

Log in with the admin email and generated password, then create a project and copy the DSN into your Sentry SDKs.

Bugsink runs as UID `14237`.

---

## Kaneo

Rootless wrapper around [Kaneo](https://github.com/usekaneo/kaneo) (self-hosted project management) with PostgreSQL 16.

### First deploy

Point Coolify at port `5173`. Coolify provides:

- `SERVICE_PASSWORD_KANEOAUTH` (`AUTH_SECRET`, use `openssl rand -hex 32`)
- `SERVICE_PASSWORD_KANEODB`

Optional:

```bash
KANEO_CLIENT_URL=https://pm.example.com   # else SERVICE_URL_KANEO_5173 (container port stripped)
```

Open the URL and create the first workspace account. Object storage (`S3_*`) is optional for uploads.

Kaneo runs as UID `1001`. The image rewrites static assets at start, so the rootfs is not read-only.

---

## OneUptime

Hardened Coolify compose for [OneUptime](https://github.com/OneUptime/oneuptime) using digest-pinned upstream images (app, nginx, probe, postgres, Valkey, ClickHouse). No custom GHCR image.

### First deploy

Point Coolify at `ingress` port `7849`. Set:

```bash
HOST=status.example.com
HTTP_PROTOCOL=https
TRUSTED_PROXY_HOPS=2
```

Coolify provides:

- `SERVICE_PASSWORD_ONEUPTIMESECRET`
- `SERVICE_PASSWORD_ONEUPTIMEENCRYPTION`
- `SERVICE_PASSWORD_ONEUPTIMEDB`
- `SERVICE_PASSWORD_ONEUPTIMEVALKEY`
- `SERVICE_PASSWORD_ONEUPTIMECLICKHOUSE`
- `SERVICE_PASSWORD_ONEUPTIMEPROBE`
- `SERVICE_PASSWORD_ONEUPTIMEPROBEKEY`

Register the first account in the UI. The bundled probe uses the compose network (`http://ingress:7849`) instead of host networking. Official docs recommend Kubernetes for large production installs.

Local smoke:

```bash
cd oneuptime
export HOST=localhost HTTP_PROTOCOL=http ONEUPTIME_HTTP_PORT=8088
export ONEUPTIME_SECRET ENCRYPTION_SECRET REGISTER_PROBE_KEY DATABASE_PASSWORD
export CLICKHOUSE_PASSWORD VALKEY_PASSWORD GLOBAL_PROBE_1_KEY
# set each to a long random value
docker compose up -d
curl -fsS http://127.0.0.1:8088/status
```

---

## SigNoz

Hardened Coolify compose for [SigNoz](https://github.com/SigNoz/signoz) using digest-pinned upstream images (signoz, otel-collector, postgres, ClickHouse + Keeper). No custom GHCR image. Layout follows the Foundry-generated compose (upstream's bundled `deploy/` files are deprecated since v0.130.0).

### First deploy

Point Coolify at `signoz` port `8080`. Coolify provides:

- `SERVICE_PASSWORD_SIGNOZDB` (postgres metastore)
- `SERVICE_PASSWORD_SIGNOZJWT` (`SIGNOZ_TOKENIZER_JWT_SECRET`, session signing)

Create the first user/org in the UI. The bundled `otel-collector` only opens its OTLP receivers (4317 gRPC, 4318 HTTP) after an org exists — the signoz opamp server rejects agents with "cannot create agent without orgId" until then, and the collector retries every 30s. To accept telemetry from outside, assign a domain to `otel-collector` port `4318` (HTTP) in Coolify.

ClickHouse auth is network-scoped (empty password, reachable only inside the compose network, no published ports).

Local smoke:

```bash
cd signoz
export SIGNOZ_DB_PASSWORD SIGNOZ_JWT_SECRET   # long random values
docker compose up -d
curl -fsS http://127.0.0.1:8080/api/v1/health
# OTLP receivers come up after the first user registers:
# curl -fsS -X POST http://127.0.0.1:4318/v1/traces -H 'Content-Type: application/json' -d '{"resourceSpans":[]}'
```

---

## Zitadel

Hardened Coolify compose for [Zitadel](https://github.com/zitadel/zitadel) v4 with digest-pinned upstream images. Runs the official v4 layout: `zitadel-api` plus the separate `zitadel-login` (Next.js) frontend, backed by PostgreSQL.

### First deploy

Zitadel v4 splits the login UI onto its own service and path. In Coolify, set **two** domain entries on this one compose app:

- `zitadel-api` -> `https://auth.example.com:8080`
- `zitadel-login` -> `https://auth.example.com/ui/v2/login` (path prefix, same host)

Required (hostname only, no scheme, no port — Coolify’s `:8080` FQDN must not be used here):

```bash
ZITADEL_EXTERNALDOMAIN=auth.example.com
```

Coolify provides:

- `SERVICE_PASSWORD_ZITADELDB` (postgres)
- `SERVICE_PASSWORD_ZITADELMASTERKEY` (`ZITADEL_MASTERKEY`, must be **exactly 32 chars**; the generated value works, and it can never be changed without losing encrypted data)
- `ZITADEL_ADMIN_PASSWORD` for the initial `zitadel-admin` user. Must satisfy the default complexity policy (8+ chars with upper, lower, number, symbol). Defaults to `Password1!` — set it or change it right after first login at `zitadel-admin@zitadel.<domain>`.

The shared `bootstrap` volume holds the `login-client` PAT written by `zitadel-api` on first boot and read by `zitadel-login`. Do not wipe it on an existing install.

Local smoke (uses the bundled v1 login on one port):

```bash
cd zitadel
export ZITADEL_DB_PASSWORD ZITADEL_MASTERKEY   # masterkey must be exactly 32 chars
docker compose up -d
curl -fsS http://localhost:8080/debug/healthz
# console at http://localhost:8080/ui/console
```

---

## ntfy

Rootless [ntfy](https://github.com/binwiederhier/ntfy) server tuned as a **public UnifiedPush / push-notification** relay. Anonymous read-write stays on so UP distributors and app servers can use random `up*` topics. Attachments and signup are off. Message cache is ephemeral tmpfs (survives process, not container recreate). Runs as UID `1000`, read-only rootfs, all caps dropped.

### First deploy

Point Coolify at `ntfy` port `8080` (domain like `https://push.example.com:8080`).

Required (no port in the URL — Coolify’s `:8080` suffix must not appear here or attachment/callback URLs break):

```bash
NTFY_BASE_URL=https://push.example.com
```

Optional:

```bash
NTFY_UPSTREAM_BASE_URL=https://ntfy.sh   # iOS relay (default)
NTFY_VISITOR_MESSAGE_DAILY_LIMIT=5000    # abuse cap per visitor IP
```

Clients: set the UnifiedPush distributor / app push server to `https://push.example.com`. Topic URLs are the secret.

### XMPP note

ntfy is the push server half. For XMPP the other half is a UP-to-XMPP rewrite proxy next to your XMPP server: `mod_unified_push` in Prosody (or ejabberd contrib), or the standalone `iNPUTmice/up` component over XEP-0114. Conversations can then act as the on-device UnifiedPush distributor over the XMPP account, no Google FCM involved.

Local smoke:

```bash
cd ntfy
docker compose up -d
curl -fsS http://127.0.0.1:8080/v1/health
curl -X POST http://127.0.0.1:8080/testtopic -d 'hello'
curl 'http://127.0.0.1:8080/testtopic/json?poll=1'
```

---

## LiveKit

Digest-pinned upstream [LiveKit](https://github.com/livekit/livekit) SFU: self-hosted WebRTC rooms for voice, video, and AI agents. Stateless single node (no Redis, no volumes), rootless as UID `1000` with a read-only rootfs. All configuration is via env vars.

### First deploy

Point Coolify at `livekit` port `7880` (HTTP/WebSocket signaling and REST API). The compose also publishes `7881` TCP (ICE-over-TCP fallback) and `7882`/udp (muxed WebRTC media) directly on the host, since media traffic cannot ride the HTTP proxy. The firewall has to allow both.

Set:

```bash
LIVEKIT_API_KEY=...        # short id, e.g. openssl rand -hex 8
LIVEKIT_API_SECRET=...     # openssl rand -hex 32
```

`rtc.use_external_ip` defaults to `true`, so ICE candidates advertise the host public IP discovered via STUN. If STUN is blocked or the box has a static public IP, set `LIVEKIT_USE_EXTERNAL_IP=false` and add `NODE_IP=<public-ip>` to the service environment.

Clients connect to `wss://<domain>` with a JWT signed by the key pair. Mint tokens with `livekit-cli create-token` or any LiveKit server SDK.

Optional built-in TURN (for clients on restrictive networks):

```bash
LIVEKIT_TURN_ENABLED=true
LIVEKIT_TURN_DOMAIN=turn.example.com
LIVEKIT_TURN_UDP_PORT=3478   # also publish 3478/udp in the compose file
# plus LIVEKIT_TURN_CERT / LIVEKIT_TURN_KEY mounts for TLS on 5349
```

Local smoke:

```bash
cd livekit
export LIVEKIT_API_KEY=devkey
export LIVEKIT_API_SECRET=$(openssl rand -hex 32)
docker compose up -d
curl -fsS http://127.0.0.1:7880/
# connect clients to ws://127.0.0.1:7880 with a token signed by devkey
```

`NODE_IP` defaults to `127.0.0.1` locally so ICE candidates point at the published ports. For LAN testing set `LIVEKIT_NODE_IP=<lan-ip>` and `LIVEKIT_BIND_IP=0.0.0.0`.

---

## Pocket ID

Distroless [Pocket ID](https://github.com/pocket-id/pocket-id): a passkeys-first OIDC provider for WebAuthn sign-on in front of self-hosted apps. Runs rootless as UID `65532` with a read-only rootfs and all caps dropped. State is SQLite on the `pocketid_data` volume.

The distroless image ships no `/app/data` directory, so a fresh named volume comes up root-owned. A one-shot `pocket-id-init` service (pinned alpine, `network_mode: none`, `CHOWN` and `DAC_READ_SEARCH` only) fixes ownership before each start.

### First deploy

Point Coolify at `pocket-id` port `1411`. `TRUST_PROXY=true` is the default since only the proxy can reach the container.

Required:

```bash
POCKETID_APP_URL=https://id.example.com   # full public origin, no port, no trailing slash
```

Coolify keeps the container routing port in the generated `SERVICE_FQDN_*`/`SERVICE_URL_*` vars, so `APP_URL` cannot be derived from them (the distroless image has no shell to strip it). Set `POCKETID_APP_URL` explicitly.

Coolify provides `SERVICE_PASSWORD_POCKETID` for `ENCRYPTION_KEY`, which protects the token signing keys. It can never be changed without losing access to existing encrypted data (rotate with `pocket-id encryption-key-rotate` if needed).

Create the admin passkey account at `https://<domain>/setup`. HTTPS is required, WebAuthn only runs in a secure context.

Optional:

```bash
POCKETID_TRUST_PROXY=10.0.0.0/8   # tighten to Coolify proxy ranges if known
POCKETID_MAXMIND_LICENSE_KEY=     # GeoLite2, audit log IP locations
POCKETID_ANALYTICS_DISABLED=true
POCKETID_VERSION_CHECK_DISABLED=true
POCKETID_ALLOW_INSECURE_CALLBACK_URLS=false  # keep false unless a client needs plain http callbacks
```

Local smoke:

```bash
cd pocketid
export POCKETID_ENCRYPTION_KEY=$(openssl rand -base64 32)
docker compose up -d
curl -fsS http://127.0.0.1:1411/.well-known/openid-configuration
# UI needs HTTPS for WebAuthn, the smoke covers the OIDC surface only
```

---

## Verdaccio

Digest-pinned upstream [Verdaccio](https://www.verdaccio.org/): a lightweight private npm proxy registry with caching. Runs rootless as UID `10001` with a read-only rootfs and all caps dropped. Package storage and `htpasswd` live on the `verdaccio_storage` volume (seeded from the image, no init container).

The Coolify compose embeds `config.yaml` via Coolify's `content:` bind so the config exists even for Docker Compose Empty. A plain `./config/...` mount without the file creates a directory and breaks the start.

### First deploy

Point Coolify at `verdaccio` port `4873` (domain like `https://npm.example.com:4873`).

Required (no port in the URL — Coolify’s `:4873` suffix must not appear here or the web UI loads forever waiting on closed port assets):

```bash
VERDACCIO_PUBLIC_URL=https://npm.example.com
```

Use `docker-compose.coolify.yml`. Auth requires login for install/publish, one bootstrap registration (`max_users: 1`), JWT expiry, and `@local/*` with no npmjs uplink.

If a previous deploy left a root-owned empty volume (from a failed init), delete the `verdaccio_storage` volume once in Coolify before redeploying so Docker can seed ownership from the image.

1. Open the UI and register the first user (password must be at least 12 characters)
2. Set `max_users: -1` in the embedded config (Edit Compose File) and redeploy so nobody else can register
3. Point clients at the registry:

```bash
npm login --registry https://npm.example.com
npm publish --registry https://npm.example.com
# scoped private packages (no public proxy):
npm publish --registry https://npm.example.com  # package name @local/my-pkg
```

Optional: change the private scope name in `config/config.yaml` from `@local/*` to your org scope, still without a `proxy` line.

Local smoke:

```bash
cd verdaccio
docker compose up -d
curl -fsS http://127.0.0.1:4873/-/ping
```

---

## PrivateBin

Digest-pinned upstream [PrivateBin](https://privatebin.info/): a zero-knowledge pastebin. The browser encrypts with AES-256-GCM before upload, so the server only stores ciphertext. Runs as UID `65534` / GID `82` with a read-only rootfs, all caps dropped, and pastes on the `privatebin_data` volume (seeded from the image, same pattern as Coolify's own PrivateBin template).

Public-ready defaults in `config/conf.php`: discussions and uploads off, burn-after-reading preselected, 2 MiB size cap, rate limit with `X-Forwarded-For`, forced expiry (no “never”), passwords enabled.

### First deploy

Point Coolify at `privatebin` port `8080` (domain like `https://paste.example.com:8080`). Use `docker-compose.coolify.yml` so `conf.php` is embedded via Coolify `content:`.

Required (no port, no trailing slash — Coolify’s `:8080` FQDN must not appear in `basepath`):

```bash
PRIVATEBIN_PUBLIC_URL=https://paste.example.com
```

HTTPS is required for a trustworthy instance (Coolify terminates TLS). Share links include the decryption key in the URL fragment (`#...`). Use a paste password for anything sensitive.

If a previous deploy left a root-owned empty volume, delete `privatebin_data` once before redeploying.

Local smoke:

```bash
cd privatebin
docker compose up -d
curl -fsS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/
```

---

## Security

- Base images pinned by digest where possible
- GitHub Actions pinned to commit SHAs
- OCI labels on published images (`org.opencontainers.image.*`)
- Cosign keyless signing (Sigstore) on every publish
- Trivy image scan (CRITICAL/HIGH reported to code scanning, wrappers may inherit upstream CVEs)
- CI uses `pull_request` with read-only permissions
- Publish only runs on `Sudo-Ivan/hardened-stacks` via the `publish` environment

Create a GitHub Environment named `publish` with required reviewers before the first release.

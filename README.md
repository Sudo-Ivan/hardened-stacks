# HardenedStacks extra

Docker stacks for [Coolify](https://coolify.io/). Each service has a local compose file and a Coolify compose file. Images publish to `ghcr.io/sudo-ivan/hardened-stacks`.

## Quick reference

| Service | Folder | Coolify port | Image |
|---------|--------|--------------|-------|
| Flarum | `flarum/` | 8080 | `ghcr.io/sudo-ivan/hardened-stacks/flarum` |
| MediaWiki | `mediawiki/` | 8080 | `ghcr.io/sudo-ivan/hardened-stacks/mediawiki` |
| Copyparty | `copyparty/` | 3923 | `ghcr.io/sudo-ivan/hardened-stacks/copyparty` |
| Forgejo | `forgejo/` | 3000 | `ghcr.io/sudo-ivan/hardened-stacks/forgejo` |
| cgit | `cgit/` | 8080 | `ghcr.io/sudo-ivan/hardened-stacks/cgit` |
| BugPin | `bugpin/` | 7300 | `ghcr.io/sudo-ivan/hardened-stacks/bugpin` |

Point your Coolify domain at the service port above. Coolify terminates HTTPS on the public URL.

---

## Flarum

Rootless forum image with bundled HardenedStacks extensions.

### First deploy

Required:

```bash
FLARUM_FORUM_TITLE=HardenedStacks Forum
FLARUM_ADMIN_EMAIL=admin@example.com
```

Coolify provides `SERVICE_PASSWORD_FLARUMADMIN` and `SERVICE_PASSWORD_FLARUMDB`.

Optional:

```bash
FLARUM_BASE_URL=https://forum.example.com
ALTCHA_HMAC_SECRET=   # auto-generated if empty
SPAM_AI_API_KEY=
SPAM_AI_BASE_URL=https://openrouter.ai/api/v1
SPAM_AI_MODEL=openai/gpt-4o-mini
FLARUM_MAINTENANCE_MODE=off   # off | banner | read_only | closed
```

The entrypoint strips `:8080` from public URLs. Set `FLARUM_BASE_URL` if you need a fixed origin.

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

Admins can permanently delete accounts from the admin user editor. Content is soft-deleted first.

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
MEDIAWIKI_LOGO_URL=https://hardened-stacks.org/static/img/logo.svg
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
FORGEJO_APP_NAME=HardenedStacks Git
FORGEJO_APP_SLOGAN=optional subtitle
FORGEJO_LOGO_URL=https://hardened-stacks.org/static/img/logo.svg
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
CGIT_SITE_TITLE=HardenedStacks Git
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

## Security

- Base images pinned by digest
- GitHub Actions pinned to commit SHAs
- CI uses `pull_request` with read-only permissions
- Publish only runs on `Sudo-Ivan/hardened-stacks` via the `publish` environment

Create a GitHub Environment named `publish` with required reviewers before the first release.

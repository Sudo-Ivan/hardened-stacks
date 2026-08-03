# HardenedStacks extra

Docker stacks for Coolify. Each service has a local compose file and a Coolify compose file.

Images are published to ghcr.io/sudo-ivan/hardened-stacks.

## Quick reference

| Service | Folder | Coolify port | Image |
|---------|--------|--------------|-------|
| Flarum | flarum/ | 8080 | ghcr.io/sudo-ivan/hardened-stacks/flarum |
| MediaWiki | mediawiki/ | 8080 | ghcr.io/sudo-ivan/hardened-stacks/mediawiki |
| Copyparty | copyparty/ | 3923 | ghcr.io/sudo-ivan/hardened-stacks/copyparty |
| Forgejo | forgejo/ | 3000 | ghcr.io/sudo-ivan/hardened-stacks/forgejo |

Point your Coolify domain at the service using the port in the table. Coolify handles HTTPS on the public URL.

---

## Flarum

Forum software. Built from source as a rootless image.

**First deploy:** set FLARUM_FORUM_TITLE and FLARUM_ADMIN_EMAIL.

**Spam protection:** the hardened-stacks-spam-protection extension is bundled. It targets spam behavior (posting speed, duplicates, link-only dumps from new accounts) rather than blocking links outright. Established members can share long link lists freely. Admins are exempt. Tune it under Admin, Extensions, Spam Protection.

**Delete users:** the hardened-stacks-delete-users extension is bundled. Admins can permanently delete accounts from Admin when editing a user. Content is soft-deleted before the account is removed.

**ALTCHA:** the hardened-stacks-altcha extension is bundled for self-hosted proof-of-work on registration (and optionally login or password reset). Set ALTCHA_HMAC_SECRET in the environment. Generate one with `openssl rand -hex 32`. No separate ALTCHA container is needed. Tune actions under Admin, Extensions, ALTCHA.

**Coolify:** uses SERVICE_PASSWORD_FLARUMADMIN and SERVICE_PASSWORD_FLARUMDB. Add ALTCHA_HMAC_SECRET as a service secret.

The entrypoint cleans up the public URL so assets load without :8080 in links. Override with FLARUM_BASE_URL if needed.

---

## MediaWiki

Wiki based on [MediaWiki](https://www.mediawiki.org/). Built from the official image as a rootless wrapper with MariaDB.

**First deploy:** set MEDIAWIKI_SITE_NAME, MEDIAWIKI_ADMIN_EMAIL, and MEDIAWIKI_ADMIN_PASSWORD locally. Coolify provides SERVICE_PASSWORD_MEDIAWIKIADMIN, SERVICE_PASSWORD_MEDIAWIKIDB, and SERVICE_PASSWORD_MEDIAWIKIROOT.

**Logo:** set MEDIAWIKI_LOGO_URL to your logo image URL. Default is the HardenedStacks logo at hardened-stacks.org.

**Data:** LocalSettings.php and uploaded images live in the mediawiki_persist volume.

The entrypoint cleans up the public URL so links load without :8080. Override with MEDIAWIKI_SITE_SERVER if needed.

---

## Copyparty

File browser based on [copyparty](https://github.com/9001/copyparty). Built from source as a rootless image.

**First deploy:** set COPYPARTY_ADMIN_PASSWORD. Coolify provides SERVICE_PASSWORD_COPYPARTYADMIN.

**Public files** go in the public folder, served at the site root. Visitors can browse and download. Uploads need admin login at /manage/.

**Behind a reverse proxy:** proxy settings are applied automatically. Optional COPYPARTY_SITE_NAME sets the browser title.

**Upgrading config:** delete copyparty.conf from the config volume and redeploy to pick up new defaults.

---

## Forgejo

Git hosting based on [Forgejo](https://forgejo.org/docs/latest/). Uses the official rootless image with a small wrapper for proxy settings.

**First deploy:** set FORGEJO_DB_PASSWORD locally. Coolify provides SERVICE_PASSWORD_FORGEJODB. Finish setup in the web installer.

**Git SSH:** expose port 2222 separately if you want git@ clone URLs over SSH.

**CAPTCHA:** Forgejo does not support ALTCHA. For self-hosted bot protection on registration, use one of these:

- Built-in image captcha: set FORGEJO_ENABLE_IMAGE_CAPTCHA=true
- mCaptcha (proof-of-work, closest to ALTCHA): use the extra compose file, create a sitekey at the mCaptcha UI, then set FORGEJO_MCAPTCHA_URL, FORGEJO_MCAPTCHA_SITEKEY, and FORGEJO_MCAPTCHA_SECRET on Forgejo:

    docker compose -f docker-compose.yml -f docker-compose.captcha.yml up -d

**Note:** Forgejo runs as UID 1000. The other stacks use 10001.

---

## Flarum backup CLI

Source lives in flarum-backup-cli/. The flarum image includes a flarum-backup command.

Export:

    flarum-backup export -o /backups/forum.tar.gz

Restore:

    flarum-backup restore -i /backups/forum.tar.gz --force

---

## Security

- Base images pinned by digest
- GitHub Actions pinned to commit SHAs
- CI uses pull_request with read-only permissions
- Publish only runs on Sudo-Ivan/hardened-stacks via the publish environment

Create a GitHub Environment named publish with required reviewers before your first release.

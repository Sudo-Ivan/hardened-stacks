# HardenedStacks extra

Docker stacks for Coolify. Each service has a local compose file and a Coolify compose file.

Images are published to ghcr.io/sudo-ivan/hardened-stacks.

## Quick reference

| Service | Folder | Coolify port | Image |
|---------|--------|--------------|-------|
| Flarum | flarum/ | 8080 | ghcr.io/sudo-ivan/hardened-stacks/flarum |
| Otter Wiki | otterwiki/ | 8080 | ghcr.io/sudo-ivan/hardened-stacks/otterwiki |
| Copyparty | copyparty/ | 3923 | ghcr.io/sudo-ivan/hardened-stacks/copyparty |
| Forgejo | forgejo/ | 3000 | ghcr.io/sudo-ivan/hardened-stacks/forgejo |

Point your Coolify domain at the service using the port in the table. Coolify handles HTTPS on the public URL.

---

## Flarum

Forum software. Built from source as a rootless image.

**First deploy:** set FLARUM_FORUM_TITLE and FLARUM_ADMIN_EMAIL.

**Coolify:** uses SERVICE_PASSWORD_FLARUMADMIN and SERVICE_PASSWORD_FLARUMDB.

The entrypoint cleans up the public URL so assets load without :8080 in links. Override with FLARUM_BASE_URL if needed.

---

## Otter Wiki

Wiki based on [redimp/otterwiki](https://github.com/redimp/otterwiki). Built from source as a rootless image.

**First deploy:** open the site and register. The first account is admin.

**Data:** everything lives in the app-data volume (sqlite, git repo, settings).

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

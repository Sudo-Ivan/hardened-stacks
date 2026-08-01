# HardenedStacks extra

Coolify ready, rootless container packs and helper tools.

## Flarum

Path: `flarum/`

Compose file: `flarum/docker-compose.yml`

Coolify compose file: `flarum/docker-compose.coolify.yml`

Assign domain `https://your-forum.example.com:8080` to the `flarum` service in Coolify. Port 8080 is the container port for the proxy. The entrypoint strips `:8080` from `FLARUM_BASE_URL` so assets use the public URL without the container port.

You can override the public URL with `FLARUM_BASE_URL=https://your-forum.example.com` if needed.

Image: `ghcr.io/sudo-ivan/hardened-stacks/flarum`

Set `FLARUM_FORUM_TITLE` and `FLARUM_ADMIN_EMAIL` before first deploy.

## Otter Wiki

Path: `otterwiki/`

Based on [redimp/otterwiki](https://github.com/redimp/otterwiki), built from source with a hardened rootless image.

Compose file: `otterwiki/docker-compose.yml`

Coolify compose file: `otterwiki/docker-compose.coolify.yml`

Assign domain `https://your-wiki.example.com:8080` to the `otterwiki` service in Coolify.

Image: `ghcr.io/sudo-ivan/hardened-stacks/otterwiki`

Register the first account after deploy. It becomes the admin account.

Data lives in the `/app-data` volume (sqlite db, git repo, settings).

## Backup CLI

Path: `flarum-backup-cli/`

Binary name inside the image: `flarum-backup`

```
flarum-backup export -o /backups/forum.tar.gz
flarum-backup restore -i /backups/forum.tar.gz --force
```

## Security notes

Base images are pinned by digest.

GitHub Actions are pinned to full commit SHAs.

CI uses `pull_request` (not `pull_request_target`) with read-only permissions.

Publish runs only for `Sudo-Ivan/hardened-stacks` on push or workflow_dispatch and uses the `publish` environment.

Create a GitHub Environment named `publish` with required reviewers before first release.

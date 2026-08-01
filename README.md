# HardenedStacks extra

Coolify ready, rootless container packs and helper tools.

## Flarum

Path: `flarum/`

Compose file: `flarum/docker-compose.yml`

Image: `ghcr.io/sudo-ivan/hardened-stacks/flarum`

Assign a Coolify domain to the `flarum` service (container port 8080).

Set `FLARUM_FORUM_TITLE` and `FLARUM_ADMIN_EMAIL` before first deploy.

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

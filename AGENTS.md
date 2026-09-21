# AGENTS.md - hardened-stacks

Rules for agents and humans adding or changing Coolify compose stacks in this repo.

## Before you touch a stack

1. Read upstream docs for that project (install, compose, config, auth, healthchecks). Do not invent ports, users, or config keys.
2. Copy patterns from a nearby stack of the same shape (single service like `ntfy/`, init+app like `zot/` or `pocketid/`, multi-service like `zitadel/`).
3. Prefer upstream digest-pinned images. Do not invent digests. Resolve with `podman pull` / `docker pull` then pin `@sha256:...`.
4. Ship both `docker-compose.yml` (local `127.0.0.1` ports) and `docker-compose.coolify.yml` (`expose`, Coolify `SERVICE_*` vars, `content:` binds when Coolify cannot mount repo files).
5. Podman-smoke the local compose before claiming done. Auth stacks must prove anonymous fail and authenticated succeed.

## Hardening defaults

- `read_only: true` plus small `tmpfs` for `/tmp` (and any other required writable paths).
- `cap_drop: [ALL]` unless a one-shot init needs `CHOWN` / `FOWNER` / `DAC_OVERRIDE`.
- `security_opt: [no-new-privileges:true]`.
- Explicit non-root `user:` matching the image (do not guess UIDs, inspect the image).
- Logging: `json-file` with `max-size` / `max-file`.
- No privileged mode, no docker.sock, no host network.

## Coolify specifics (this is where we keep burning time)

### Public URLs and `:port` leaks

Coolify domain entries look like `https://app.example.com:8080`. The `:8080` is the *container* port for Traefik, not part of the public URL. Generated `SERVICE_FQDN_*` / `SERVICE_URL_*` often still include that port.

- Never bake `SERVICE_FQDN_*` / `SERVICE_URL_*` into browser-facing absolute URLs unless an entrypoint strips the routing port.
- Stacks without a stripper need an explicit public URL env (`NTFY_BASE_URL`, `VERDACCIO_PUBLIC_URL`, `PRIVATEBIN_PUBLIC_URL`, `MIROTALK_HOST`, Zitadel `EXTERNALDOMAIN`, Pocket ID public URL, OneUptime `HOST`).
- Document the required URL env in README. Say "no port, no trailing slash" when that matters.

### `content:` binds

Coolify cannot reliably mount arbitrary repo files. Embed config with Coolify `content:` on a bind. Local compose keeps a normal file bind under `config/`. Keep both in sync.

`docker compose config` will reject `content:`. That is expected. Only Coolify understands it.

### Init containers

- Prefer image-seeded volumes (Verdaccio, PrivateBin) over init when the upstream image already owns the data dir.
- Never run `apk add` / `apt-get` on a `read_only` rootfs. Pick an image that already has the tool (`httpd:alpine` for `htpasswd`, not alpine+apk).
- Init must log clearly and `set -eu`. Silent chown that exits 0 on failure is forbidden.
- Mark init with `exclude_from_hc: true` and `restart: "no"`. App uses `depends_on: { init: { condition: service_completed_successfully } }`.
- If a previous failed init left a root-owned empty volume, document "delete the volume once and redeploy".

### Passwords and secrets

- Coolify: `SERVICE_PASSWORD_<NAME>` (auto-generated). Map into the app env.
- Local: require `VAR=${VAR:?}` so missing secrets fail fast.
- Do not ship upstream demo credentials (public TURN accounts, default `admin/admin`, survey/analytics endpoints left on).

## Per-stack checklist

- [ ] Docs read (link them in the PR / commit body when non-obvious)
- [ ] Digest pin current as of the change
- [ ] Local + Coolify compose
- [ ] README table row + section
- [ ] CI smoke job in `.github/workflows/ci.yml`
- [ ] Public URL / port-leak handled
- [ ] Podman smoke: up, health or auth probe, down `-v`

## Branding

Quad4 mark (icon only, not the lockup/wordmark) lives in `branding/` from https://quad4.io/branding:

- `branding/mark.svg` - void mark, use as favicon / app icon
- `branding/mark-180.png` - touch icon

Rules:

- Prefer the mark. Never use lockup/wordmark assets in product chrome unless asked.
- Keep void `#0A0A0B` / paper `#FAFAFA` only. No recolor, stretch, or effects.
- **Zot UI** embeds its own assets and has no logo config hook. Do not pretend we rebranded it. Point Coolify resource icon at `branding/mark.svg` instead.
- Apps that allow a logo mount (e.g. MiroTalk `public/images/logo.svg`) may bind `branding/mark.svg`.

## Do not

- Generate README / markdown the user did not ask for (except updating the existing README when adding a stack).
- Add CDNs, trackers, or upstream telemetry that can be disabled.
- Leave `apk`/`apt` install steps in production init.
- Commit `.env` files with real secrets.
- "Fix" Coolify by publishing host ports that Traefik already routes. Use `expose`.

## Known pain lessons

| Failure | Fix |
|---------|-----|
| Alpine init `apk` on `read_only` exits 99 / read-only FS | Use `httpd:alpine` (or similar) that already has `htpasswd` |
| Silent chown init "succeeds", app cannot write | Remove init, seed from image, or make init fail loud |
| Verdaccio / ntfy UI hangs or wrong links | Public URL env had Coolify `:port`. Strip or set clean URL |
| Coolify config bind empty | Use `content:` in `docker-compose.coolify.yml` |
| Guessed UID / healthcheck binary | Inspect image `User`, `Entrypoint`, and what tools exist |

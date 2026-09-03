# syntax=docker/dockerfile:1@sha256:87999aa3d42bdc6bea60565083ee17e86d1f3339802f543c0d03998580f9cb89

ARG FORGEJO_IMAGE=codeberg.org/forgejo/forgejo:16-rootless@sha256:214f4ae63ee78be1e445e58573c88dc7215e72091210852e0df94eaac1a25685

FROM ${FORGEJO_IMAGE}

USER root
COPY --chmod=0555 docker/entrypoint.sh /usr/local/bin/pmg-entrypoint.sh
USER 1000:1000

ENTRYPOINT ["/usr/bin/dumb-init", "--", "/usr/local/bin/pmg-entrypoint.sh"]

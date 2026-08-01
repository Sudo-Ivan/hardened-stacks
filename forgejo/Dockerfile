# syntax=docker/dockerfile:1@sha256:87999aa3d42bdc6bea60565083ee17e86d1f3339802f543c0d03998580f9cb89

ARG FORGEJO_IMAGE=codeberg.org/forgejo/forgejo:16-rootless@sha256:23ccc146e6dc2cd1f5c5435909baae64db873201717f1726490dae649283e6cd

FROM ${FORGEJO_IMAGE}

USER root
COPY --chmod=0555 docker/entrypoint.sh /usr/local/bin/pmg-entrypoint.sh
USER 1000:1000

ENTRYPOINT ["/usr/bin/dumb-init", "--", "/usr/local/bin/pmg-entrypoint.sh"]

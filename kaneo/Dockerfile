# syntax=docker/dockerfile:1@sha256:87999aa3d42bdc6bea60565083ee17e86d1f3339802f543c0d03998580f9cb89

ARG KANEO_IMAGE=ghcr.io/usekaneo/kaneo:latest@sha256:1f1fc35e45da5dd4a991ce741e2492510068449db3e37b8e06a4de13a91bce2a

FROM ${KANEO_IMAGE}

USER root
COPY --chmod=0555 docker/entrypoint.sh /usr/local/bin/pmg-entrypoint.sh
USER 1001:1001

ENTRYPOINT ["/usr/local/bin/pmg-entrypoint.sh"]
CMD ["/usr/local/bin/kaneo-entrypoint.sh"]

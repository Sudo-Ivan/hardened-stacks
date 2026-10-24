# syntax=docker/dockerfile:1@sha256:4edf897a3ffa55b89f906fc8cc78afdb3f1834cc9c7083565e611a8a7d5fe99e

ARG FORGEJO_IMAGE=codeberg.org/forgejo/forgejo:16-rootless@sha256:51b47921660e1ec7127f094208fe06901ba57df4fe6d59fcccc3a2419514a77f

FROM ${FORGEJO_IMAGE}

USER root
COPY --chmod=0555 docker/entrypoint.sh /usr/local/bin/pmg-entrypoint.sh
USER 1000:1000

ENTRYPOINT ["/usr/bin/dumb-init", "--", "/usr/local/bin/pmg-entrypoint.sh"]

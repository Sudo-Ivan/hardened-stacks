# syntax=docker/dockerfile:1@sha256:4edf897a3ffa55b89f906fc8cc78afdb3f1834cc9c7083565e611a8a7d5fe99e

ARG BUGPIN_IMAGE=registry.arantic.cloud/bugpin/bugpin:latest@sha256:317c79d9154f3ecc7e9714a1edfbd4510737a4dffd20c3b64b67b99046539c96

FROM ${BUGPIN_IMAGE}

USER root
COPY --chmod=0555 docker/entrypoint.sh /usr/local/bin/pmg-entrypoint.sh
USER 1000:1000

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/pmg-entrypoint.sh"]
CMD ["bun", "run", "src/server/index.ts"]

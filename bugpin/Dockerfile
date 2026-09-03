# syntax=docker/dockerfile:1@sha256:87999aa3d42bdc6bea60565083ee17e86d1f3339802f543c0d03998580f9cb89

ARG BUGPIN_IMAGE=registry.arantic.cloud/bugpin/bugpin:latest@sha256:174abd504dd145695a7449b397f14f1eb683b48873b488af903a53ff264ae082

FROM ${BUGPIN_IMAGE}

USER root
COPY --chmod=0555 docker/entrypoint.sh /usr/local/bin/pmg-entrypoint.sh
USER 1000:1000

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/pmg-entrypoint.sh"]
CMD ["bun", "run", "src/server/index.ts"]

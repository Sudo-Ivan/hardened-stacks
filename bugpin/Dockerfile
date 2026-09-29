# syntax=docker/dockerfile:1@sha256:ecfaec9ed6d810b56388c508f4121597bfbba70d41a6dfeee4d8cad5f295fc32

ARG BUGPIN_IMAGE=registry.arantic.cloud/bugpin/bugpin:latest@sha256:c067f08f469a3068f0465194d091f91a9a0d5af15f3c0939a83c28470ec79a7e

FROM ${BUGPIN_IMAGE}

USER root
COPY --chmod=0555 docker/entrypoint.sh /usr/local/bin/pmg-entrypoint.sh
USER 1000:1000

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/pmg-entrypoint.sh"]
CMD ["bun", "run", "src/server/index.ts"]

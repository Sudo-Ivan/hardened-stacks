# syntax=docker/dockerfile:1@sha256:ecfaec9ed6d810b56388c508f4121597bfbba70d41a6dfeee4d8cad5f295fc32

ARG KANEO_IMAGE=ghcr.io/usekaneo/kaneo:latest@sha256:af576bdb2a6dffaa33f67d715f2d83b30afbb052b44cf4b036e76bed9857319a

FROM ${KANEO_IMAGE}

USER root
COPY --chmod=0555 docker/entrypoint.sh /usr/local/bin/pmg-entrypoint.sh
USER 1001:1001

ENTRYPOINT ["/usr/local/bin/pmg-entrypoint.sh"]
CMD ["/usr/local/bin/kaneo-entrypoint.sh"]

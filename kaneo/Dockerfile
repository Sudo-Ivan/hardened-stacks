# syntax=docker/dockerfile:1@sha256:87999aa3d42bdc6bea60565083ee17e86d1f3339802f543c0d03998580f9cb89

ARG KANEO_IMAGE=ghcr.io/usekaneo/kaneo:latest@sha256:af576bdb2a6dffaa33f67d715f2d83b30afbb052b44cf4b036e76bed9857319a

FROM ${KANEO_IMAGE}

USER root
COPY --chmod=0555 docker/entrypoint.sh /usr/local/bin/pmg-entrypoint.sh
USER 1001:1001

ENTRYPOINT ["/usr/local/bin/pmg-entrypoint.sh"]
CMD ["/usr/local/bin/kaneo-entrypoint.sh"]

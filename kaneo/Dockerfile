# syntax=docker/dockerfile:1@sha256:4edf897a3ffa55b89f906fc8cc78afdb3f1834cc9c7083565e611a8a7d5fe99e

ARG KANEO_IMAGE=ghcr.io/usekaneo/kaneo:latest@sha256:3df62194406061d9a162409b4c08d8af5225e423ce813d509694cea616ac9d23

FROM ${KANEO_IMAGE}

USER root
COPY --chmod=0555 docker/entrypoint.sh /usr/local/bin/pmg-entrypoint.sh
USER 1001:1001

ENTRYPOINT ["/usr/local/bin/pmg-entrypoint.sh"]
CMD ["/usr/local/bin/kaneo-entrypoint.sh"]

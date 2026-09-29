# syntax=docker/dockerfile:1@sha256:ecfaec9ed6d810b56388c508f4121597bfbba70d41a6dfeee4d8cad5f295fc32

ARG FORGEJO_IMAGE=codeberg.org/forgejo/forgejo:16-rootless@sha256:5effb7305584aca479b29fde6f9631a6dbe86ae798ae02eeea33a3666f0c0bf8

FROM ${FORGEJO_IMAGE}

USER root
COPY --chmod=0555 docker/entrypoint.sh /usr/local/bin/pmg-entrypoint.sh
USER 1000:1000

ENTRYPOINT ["/usr/bin/dumb-init", "--", "/usr/local/bin/pmg-entrypoint.sh"]

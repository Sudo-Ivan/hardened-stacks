# syntax=docker/dockerfile:1@sha256:ecfaec9ed6d810b56388c508f4121597bfbba70d41a6dfeee4d8cad5f295fc32

ARG BUGSINK_IMAGE=bugsink/bugsink:2@sha256:c5e3657451f4ba1ddb145a5118a4347314206cc5ba98f40e5f268c6c0975963e

FROM ${BUGSINK_IMAGE}

USER root
COPY --chmod=0555 docker/entrypoint.sh /usr/local/bin/pmg-entrypoint.sh
USER 14237:14237

ENTRYPOINT ["/usr/local/bin/pmg-entrypoint.sh"]
CMD [ "monofy", "bugsink-show-version", "&&", "bugsink-manage", "check", "--deploy", "--fail-level", "WARNING", "&&", "bugsink-manage", "migrate", "snappea", "--database=snappea", "&&", "bugsink-manage", "migrate", "&&", "bugsink-manage", "prestart", "&&", "gunicorn", "--config", "gunicorn.docker.conf.py", "--bind=0.0.0.0:$PORT", "--access-logfile", "-", "bugsink.wsgi", "|||", "bugsink-runsnappea"]

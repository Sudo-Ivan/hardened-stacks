# syntax=docker/dockerfile:1@sha256:4edf897a3ffa55b89f906fc8cc78afdb3f1834cc9c7083565e611a8a7d5fe99e

ARG BUGSINK_IMAGE=bugsink/bugsink:2@sha256:a013fe0da745b753d65f9816020d36f91620e6d1c62ac22d82463b3a6ec2d71e

FROM ${BUGSINK_IMAGE}

USER root
COPY --chmod=0555 docker/entrypoint.sh /usr/local/bin/pmg-entrypoint.sh
USER 14237:14237

ENTRYPOINT ["/usr/local/bin/pmg-entrypoint.sh"]
CMD [ "monofy", "bugsink-show-version", "&&", "bugsink-manage", "check", "--deploy", "--fail-level", "WARNING", "&&", "bugsink-manage", "migrate", "snappea", "--database=snappea", "&&", "bugsink-manage", "migrate", "&&", "bugsink-manage", "prestart", "&&", "gunicorn", "--config", "gunicorn.docker.conf.py", "--bind=0.0.0.0:$PORT", "--access-logfile", "-", "bugsink.wsgi", "|||", "bugsink-runsnappea"]

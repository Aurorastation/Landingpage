FROM webdevops/php-nginx:8.0-alpine

COPY docker/nginx/10-location-root.conf /opt/docker/etc/nginx/vhost.common.d/

ENV WEB_DOCUMENT_ROOT /app
WORKDIR /app
COPY web .

RUN chown -R application:application .
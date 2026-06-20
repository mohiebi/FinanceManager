#!/usr/bin/env bash
set -e

APP_ROOT="/home/site/wwwroot"
PUBLIC_ROOT="${APP_ROOT}/public"
NGINX_CONFIG="${APP_ROOT}/default"
NGINX_CONF_D="/etc/nginx/conf.d/default.conf"
NGINX_SITES_AVAILABLE="/etc/nginx/sites-available/default"

if [ -f "${APP_ROOT}/artisan" ]; then
  cd "${APP_ROOT}"
  rm -f public/hot
  php artisan optimize:clear --no-interaction
  php artisan migrate --force --no-interaction
fi

if [ -d "${PUBLIC_ROOT}" ]; then
  if [ -f "${NGINX_CONFIG}" ]; then
    if [ -f "${NGINX_CONF_D}" ]; then
      cp "${NGINX_CONFIG}" "${NGINX_CONF_D}"
    fi

    if [ -f "${NGINX_SITES_AVAILABLE}" ]; then
      cp "${NGINX_CONFIG}" "${NGINX_SITES_AVAILABLE}"
    fi

    service nginx reload || true
  fi
fi

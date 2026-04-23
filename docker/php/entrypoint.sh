#!/bin/sh
set -e

mkdir -p var/cache var/log var/sessions

php bin/console lexik:jwt:generate-keypair --skip-if-exists --env=prod

php bin/console cache:warmup --env=prod

chown -R www-data:www-data var config/jwt
chmod -R 775 var config/jwt

nginx -g "daemon off;" &

exec php-fpm

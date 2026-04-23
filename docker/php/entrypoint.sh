#!/bin/sh
set -e

mkdir -p var/cache var/log var/sessions
chown -R www-data:www-data var
chmod -R 775 var

php bin/console lexik:jwt:generate-keypair --skip-if-exists --env=prod

php bin/console cache:warmup --env=prod

nginx -g "daemon off;" &

exec php-fpm

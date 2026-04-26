#!/bin/sh
set -e

# En dev, le volume bind ./:/var/www gère le code et les permissions depuis l'hôte.
# On ne fait le setup (cache, JWT, chown, nginx) qu'en prod.
if [ "$APP_ENV" = "prod" ]; then
    # nginx et PHP-FPM sont dans le même container en prod.
    # Alias "php" → 127.0.0.1 pour que la même config nginx (fastcgi_pass php:9000) marche en dev (réseau Docker) et en prod.
    echo "127.0.0.1 php" >> /etc/hosts

    mkdir -p var/cache var/log var/sessions

    php bin/console lexik:jwt:generate-keypair --skip-if-exists --env=prod
    php bin/console assets:install public --env=prod
    php bin/console importmap:install --env=prod
    php bin/console asset-map:compile --env=prod
    php bin/console cache:warmup --env=prod

    chown -R www-data:www-data var config/jwt public/assets
    chmod -R 775 var config/jwt

    nginx -g "daemon off;" &
fi

exec php-fpm

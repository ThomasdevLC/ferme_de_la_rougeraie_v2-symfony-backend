#!/bin/sh
set -e

# En dev, le volume bind ./:/var/www gère le code et les permissions depuis l'hôte.
# On ne fait le setup (cache, JWT, chown, nginx) qu'en prod.
if [ "$APP_ENV" = "prod" ]; then
    # nginx et PHP-FPM sont dans le même container en prod.
    # Alias "php" → 127.0.0.1 pour que la même config nginx (fastcgi_pass php:9000) marche en dev (réseau Docker) et en prod.
    echo "127.0.0.1 php" >> /etc/hosts

    mkdir -p var/cache var/log var/sessions

    # Migrations de schéma : jouées par le conteneur web uniquement (jamais le
    # worker, pour éviter une double exécution concurrente), après avoir attendu
    # que la base soit joignable (elle peut démarrer plus lentement que l'app).
    if [ "$ROLE" != "worker" ]; then
        echo "Attente de la base de données..."
        ATTEMPTS=0
        until php bin/console dbal:run-sql "SELECT 1" --env=prod >/dev/null 2>&1; do
            ATTEMPTS=$((ATTEMPTS + 1))
            if [ "$ATTEMPTS" -ge 30 ]; then
                echo "Base de données injoignable après 30 tentatives, abandon." >&2
                exit 1
            fi
            echo "  base indisponible, tentative $ATTEMPTS/30..."
            sleep 2
        done

        echo "Application des migrations..."
        php bin/console doctrine:migrations:migrate --no-interaction --env=prod
    fi

    php bin/console lexik:jwt:generate-keypair --skip-if-exists --env=prod
    php bin/console assets:install public --env=prod
    php bin/console importmap:install --env=prod
    php bin/console asset-map:compile --env=prod
    php bin/console cache:warmup --env=prod

    chown -R www-data:www-data var config/jwt public/assets
    chmod -R 775 var config/jwt

    if [ "$ROLE" != "worker" ]; then
        nginx -g "daemon off;" &
    fi
fi

exec "$@"

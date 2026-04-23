# ============================================================
# Stage 1 — builder : installe les dépendances Composer
# ============================================================
FROM php:8.2-fpm AS builder

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libonig-dev \
    libzip-dev \
    zip \
    curl \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    intl \
    opcache \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copie des fichiers Composer en premier pour profiter du cache Docker
COPY composer.json composer.lock ./

RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copie du reste du code
COPY . .

# Fichier .env minimal requis par Symfony au boot (les vraies vars viennent de Dokploy)
RUN echo "APP_ENV=prod" > .env

# Installation des assets JS (importmap) et compilation pour EasyAdmin.
# Les env vars ci-dessous ne servent qu'au build Symfony — les vraies vars viennent de Dokploy au runtime.
ENV APP_SECRET=build DATABASE_URL=mysql://build@localhost/build JWT_PASSPHRASE=build MESSENGER_TRANSPORT_DSN=sync:// MAILER_DSN=null://null
RUN php bin/console importmap:install \
    && php bin/console asset-map:compile

# ============================================================
# Stage 2 — image finale (nginx + PHP-FPM dans le même container)
# ============================================================
FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    libicu-dev \
    libonig-dev \
    libzip-dev \
    zip \
    nginx \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    intl \
    opcache \
    zip

WORKDIR /var/www

# Copie du code + vendor depuis le builder
COPY --from=builder /var/www /var/www

ENV APP_ENV=prod

RUN mkdir -p public/uploads/images \
    && chown -R www-data:www-data public/uploads \
    && chmod -R 775 public/uploads

RUN mkdir -p config/jwt \
    && chown -R www-data:www-data config/jwt \
    && chmod -R 775 config/jwt

RUN rm -f /etc/nginx/sites-enabled/default

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]

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

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]

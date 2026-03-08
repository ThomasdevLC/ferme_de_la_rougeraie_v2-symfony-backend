FROM php:8.2-fpm

# Installation dépendances système
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libonig-dev \
    libzip-dev \
    zip \
    curl

# Extensions PHP nécessaires à Symfony
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    intl \
    opcache \
    zip

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

CMD ["php-fpm"]
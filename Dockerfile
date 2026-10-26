FROM php:8.4-fpm

WORKDIR /var/www

# System dependencies and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl zip unzip libpng-dev libonig-dev libxml2-dev libsqlite3-dev \
        libzip-dev libicu-dev libpq-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd intl zip opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --no-scripts --no-autoloader

COPY . .

RUN composer dump-autoload --optimize \
    && chmod +x docker-entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/var/www/docker-entrypoint.sh"]
CMD ["php-fpm"]

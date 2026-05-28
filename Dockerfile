# syntax=docker/dockerfile:1

FROM php:8.5-cli-alpine AS vendor

WORKDIR /app

RUN apk add --no-cache \
    git \
    unzip \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

FROM node:24-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY --from=vendor /app/vendor ./vendor
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.5-fpm-alpine AS runtime

WORKDIR /var/www/html

RUN apk add --no-cache \
    ca-certificates \
    curl \
    gettext \
    icu-libs \
    libzip \
    nginx \
    postgresql-libs \
    && apk add --no-cache --virtual .build-deps \
    ${PHPIZE_DEPS} \
    icu-dev \
    libzip-dev \
    linux-headers \
    postgresql-dev \
    && docker-php-ext-install \
    bcmath \
    intl \
    pcntl \
    pdo_pgsql \
    zip \
    && apk del .build-deps

COPY --from=vendor /app ./
COPY --from=assets /app/public/build ./public/build

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf.template /etc/nginx/templates/default.conf.template
COPY docker/php.ini /usr/local/etc/php/conf.d/production.ini
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint

RUN chmod +x /usr/local/bin/docker-entrypoint \
    && mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080

ENTRYPOINT ["docker-entrypoint"]
CMD ["nginx", "-g", "daemon off;"]

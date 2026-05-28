ARG PHP_VERSION=8.1
FROM php:${PHP_VERSION}-cli-alpine

RUN apk add --no-cache git unzip $PHPIZE_DEPS sqlite-dev libxml2-dev oniguruma-dev icu-dev linux-headers \
    && docker-php-ext-install pdo pdo_sqlite \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    XDEBUG_MODE=off

WORKDIR /app

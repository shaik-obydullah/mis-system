FROM php:8.5-fpm-alpine

RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        zip \
        unzip \
        git \
        curl \
        oniguruma-dev \
    && docker-php-ext-install \
        pdo_mysql \
        intl \
        zip \
    && apk add --no-cache libpng-dev freetype-dev libjpeg-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN addgroup -g 1000 -S www \
    && adduser -u 1000 -S www -G www \
    && chown -R www:www /var/www

WORKDIR /var/www/html

USER www

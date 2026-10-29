# docker/php/Dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    curl \
    zip \
    unzip \
    git \
    libpng-dev \
    libxml2-dev \
    postgresql-dev \
    imap-dev \
    openssl-dev \
    krb5-dev \
    icu-dev \
    libzip-dev \
    linux-headers \
    $PHPIZE_DEPS

RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install pdo pdo_pgsql pgsql pcntl bcmath gd intl imap zip

RUN pecl install redis \
    && docker-php-ext-enable redis

RUN apk del $PHPIZE_DEPS \
    && rm -rf /var/cache/apk/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

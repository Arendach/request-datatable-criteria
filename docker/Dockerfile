FROM php:8.1-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && docker-php-ext-install pdo pdo_mysql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN pecl install xdebug && docker-php-ext-enable xdebug

WORKDIR /arendach-request-datatable-criteria

ENV COMPOSER_HOME=/tmp/composer
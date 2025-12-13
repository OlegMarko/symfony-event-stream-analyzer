FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git \
    libpq-dev \
    librabbitmq-dev \
    libzip-dev \
    libicu-dev

RUN docker-php-ext-install pdo pdo_pgsql opcache intl zip
RUN pecl install redis amqp && docker-php-ext-enable redis amqp

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]
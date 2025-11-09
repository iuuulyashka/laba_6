FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo pdo_mysql

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY ./www/composer.json ./
RUN composer install --no-dev --no-cache

COPY ./www ./

RUN chown -R www-data:www-data /var/www/html

CMD ["php-fpm", "-F"]
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-scripts \
    --no-progress \
    --optimize-autoloader

FROM php:8.3-apache

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev msmtp-mta unzip \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache/cesizen.conf /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN mkdir -p var/cache var/log public/bundles \
    && chown -R www-data:www-data var public

COPY docker/api-entrypoint.sh /usr/local/bin/api-entrypoint.sh
RUN chmod +x /usr/local/bin/api-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/api-entrypoint.sh"]
CMD ["apache2-foreground"]

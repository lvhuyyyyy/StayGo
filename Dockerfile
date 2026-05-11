FROM php:8.3-cli
RUN docker-php-ext-install mysqli pdo_mysql
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . /app
WORKDIR /app
RUN composer install --no-dev --optimize-autoloader --no-interaction
CMD php -d output_buffering=On -d display_errors=Off -d error_reporting=E_ALL -S 0.0.0.0:${PORT:-8080}

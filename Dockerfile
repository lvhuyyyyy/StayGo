FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
        unzip git libzip-dev libonig-dev default-mysql-client \
    && docker-php-ext-install mysqli pdo_mysql zip mbstring \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . /app
WORKDIR /app

RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

CMD ["/entrypoint.sh"]

FROM php:8.3-cli
RUN docker-php-ext-install mysqli pdo_mysql
COPY . /app
WORKDIR /app
CMD php -S 0.0.0.0:${PORT:-8080}

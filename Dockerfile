FROM php:8.3-cli
RUN docker-php-ext-install mysqli pdo_mysql
COPY . /app
WORKDIR /app
CMD php -d output_buffering=On -d display_errors=Off -d error_reporting=E_ALL -S 0.0.0.0:${PORT:-8080}

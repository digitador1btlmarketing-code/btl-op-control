FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    zip \
    sqlite3 \
    libsqlite3-dev \
    libpq-dev

RUN docker-php-ext-install pdo pdo_sqlite pdo_pgsql pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN cp php.ini /usr/local/etc/php/conf.d/uploads.ini

RUN composer install --no-dev --optimize-autoloader

EXPOSE 10000

CMD ["sh", "/app/docker-entrypoint.sh"]
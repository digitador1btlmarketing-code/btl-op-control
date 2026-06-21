FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    unzip git zip sqlite3 libsqlite3-dev curl \
    && docker-php-ext-install pdo pdo_sqlite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node:20 /usr/local/bin/node /usr/local/bin/node
COPY --from=node:20 /usr/local/bin/npm /usr/local/bin/npm

WORKDIR /app

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN npm install
RUN npm run build

RUN cp .env.example .env
RUN touch database/database.sqlite
RUN php artisan key:generate --force
RUN php artisan migrate --force
RUN php artisan storage:link || true

CMD php artisan serve --host=0.0.0.0 --port=${PORT}
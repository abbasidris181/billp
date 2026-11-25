FROM node:24-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

FROM php:8.5-cli
WORKDIR /app
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pcntl
RUN printf 'upload_max_filesize=10M\npost_max_size=12M\n' > "$PHP_INI_DIR/conf.d/portal-uploads.ini"
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader
COPY . .
COPY --from=frontend /app/public/build ./public/build
RUN composer dump-autoload --no-dev --optimize --no-interaction
EXPOSE 8000
CMD ["sh", "-ec", "test -f .env || cp .env.example .env; if [ -z \"$APP_KEY\" ]; then unset APP_KEY; if ! grep -q '^APP_KEY=.' .env; then php artisan key:generate; fi; fi; php artisan migrate --force; exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload"]

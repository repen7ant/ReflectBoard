FROM node:20-alpine AS frontend_builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.4-fpm-alpine AS base
RUN apk add --no-cache \
    git curl libpng-dev libzip-dev zip unzip $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-install pdo_mysql gd zip bcmath opcache \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
EXPOSE 9000

FROM base AS development
CMD ["php-fpm"]

FROM base AS production
COPY . .
COPY --from=frontend_builder /app/public/build ./public/build
RUN composer install --no-dev --optimize-autoloader --no-scripts
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache
USER www-data
CMD ["php-fpm"]

# ===========================================
# BASE STAGE - Common dependencies
# ===========================================
ARG IMAGICK_VERSION=3.8.1

FROM php:8.4-fpm-alpine AS base

ARG IMAGICK_VERSION

# Install system dependencies
# fcgi : cgi-fcgi, requis par le healthcheck php-fpm (cf. deploy/docker/www.conf)
RUN apk add --no-cache \
    git \
    curl \
    fcgi \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    postgresql-dev \
    oniguruma-dev \
    icu-dev \
    libwebp-dev

# Install fonts for watermarks (separate step, optional)
RUN apk add --no-cache ttf-dejavu || echo "Font installation skipped"

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip intl opcache

# Imagick est le driver EFFECTIF (ImageProcessingService::USE_IMAGICK_DRIVER = true).
# Version épinglée et échec bloquant : sans cela deux builds du même commit peuvent
# produire deux moteurs de rendu différents (filigrane et performances différents).
RUN apk add --no-cache --virtual .build-deps autoconf g++ make \
    && apk add --no-cache imagemagick imagemagick-dev \
    && yes '' | pecl install imagick-${IMAGICK_VERSION} \
    && docker-php-ext-enable imagick \
    && php -m | grep -q '^imagick$' \
    && pecl clear-cache \
    && apk del .build-deps

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# ===========================================
# DEVELOPMENT STAGE - PHP-FPM for Docker Compose
# ===========================================
FROM base AS development

# Copy composer files first for caching
COPY composer.json composer.lock ./

# Install dependencies (with dev)
RUN composer install --no-scripts --no-autoloader

# Copy application files
COPY . .

# bootstrap/cache n'est pas versionne : le creer avant dump-autoload sinon
# package:discover echoue ("directory must be present and writable").
RUN mkdir -p bootstrap/cache

# Complete composer install
RUN composer dump-autoload --optimize

# Set permissions
RUN chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www/storage \
    && chown -R www-data:www-data /var/www/bootstrap/cache

# Expose PHP-FPM port
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]

# ===========================================
# PRODUCTION-FPM STAGE - l'image EST l'artefact
# ===========================================
FROM base AS production-fpm

# Dependances d'abord, pour que le cache de couches survive a un changement de code.
COPY composer.json composer.lock ./
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs \
    && composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Aucun `config:cache` ici : il figerait l'environnement du BUILD, pas celui d'execution.
# Les caches sont chauffes au deploiement, une fois les variables reelles presentes.
#
# Aucun `migrate` non plus. Une migration au demarrage du conteneur s'execute sans
# surveillance a chaque redemarrage, et trois services (api, queue, scheduler) partagent
# cette image : ils la lanceraient en parallele. C'est une etape explicite du deploiement.

EXPOSE 9000

CMD ["php-fpm"]

# ===========================================
# BASE STAGE - Common dependencies
# ===========================================
FROM php:8.4-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
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
# PRODUCTION STAGE - For Render (artisan serve)
# ===========================================
FROM php:8.4-cli-alpine AS production

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
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

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . /var/www

# Install dependencies (production only)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache

# Create storage symlink
RUN php artisan storage:link || true

# Cache config for production
RUN php artisan config:cache || true
RUN php artisan route:cache || true
RUN php artisan view:cache || true

# Expose port (Render uses PORT env variable)
EXPOSE 8000

# Start scheduler in background, then artisan serve - run migrations first
CMD php artisan migrate --force && php artisan schedule:work >> /var/www/storage/logs/scheduler.log 2>&1 & php artisan serve --host=0.0.0.0 --port=${PORT:-8000}

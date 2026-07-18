FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    sqlite-dev \
    mysql-client \
    docker-cli

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql pdo_sqlite xml zip bcmath gd

# Copy Composer from the official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/web

# Copy dependency configuration
COPY composer.json composer.lock ./

# Install dependencies (ignoring scripts as they might need full codebase setup)
RUN composer install --no-interaction --no-scripts --no-autoloader --no-dev

# Copy application source code
COPY . .

# Generate autoload files
RUN composer dump-autoload --no-dev --optimize

# Copy entrypoint script and make it executable
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set correct ownership for Laravel folders
RUN chown -R www-data:www-data /var/web

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

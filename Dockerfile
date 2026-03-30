FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    git \
    curl \
    netcat-openbsd \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    icu-dev \
    nodejs \
    npm \
    ca-certificates \
    tzdata

# Set timezone to Johannesburg
ENV TZ=Africa/Johannesburg
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mbstring \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies (update lock for any newly added packages, then install)
RUN composer update --no-dev --optimize-autoloader --no-interaction

# Generate PWA icons (requires GD)
RUN php generate-icons.php

# Install Node dependencies and build frontend assets
RUN npm install --legacy-peer-deps && npm run build && rm -rf node_modules

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Create required directories
RUN mkdir -p /var/log/supervisor /run/nginx /var/log/php

# Copy and set entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Expose port
EXPOSE 80

# Start with entrypoint
ENTRYPOINT ["/entrypoint.sh"]

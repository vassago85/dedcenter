# composer.lock requires php >= 8.4 (symfony/* 8.x line) so the base image is pinned to 8.4-alpine.
FROM php:8.4-fpm-alpine

# Avoid dl-cdn TLS/transient failures during apk (common when install-php-extensions runs apk update)
RUN sed -i 's|dl-cdn.alpinelinux.org|mirror.leaseweb.com|g' /etc/apk/repositories

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

# Install PHP extensions (install-php-extensions handles build deps + cleanup automatically).
# NOTE: redis is intentionally excluded here — pecl.php.net release listings/downloads are
# intermittently unavailable and break the build. We compile phpredis from its GitHub release
# tarball below instead, which avoids PECL entirely.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# Install phpredis from GitHub source (PECL is unreliable). Pin to a known release.
ENV PHPREDIS_VERSION=6.3.0
RUN set -eux; \
    apk add --no-cache --virtual .phpredis-build-deps $PHPIZE_DEPS; \
    curl -fsSL "https://github.com/phpredis/phpredis/archive/refs/tags/${PHPREDIS_VERSION}.tar.gz" -o /tmp/phpredis.tar.gz; \
    mkdir -p /tmp/phpredis; \
    tar -xzf /tmp/phpredis.tar.gz -C /tmp/phpredis --strip-components=1; \
    cd /tmp/phpredis; \
    phpize; \
    ./configure; \
    make -j"$(nproc)"; \
    make install; \
    docker-php-ext-enable redis; \
    cd /; \
    rm -rf /tmp/phpredis /tmp/phpredis.tar.gz; \
    apk del .phpredis-build-deps

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

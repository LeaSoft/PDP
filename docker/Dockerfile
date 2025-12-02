# ======================
# Base PHP-FPM image
# ======================
FROM php:8.2-fpm AS base

WORKDIR /var/www/html

# System packages, PHP extensions, user, FPM config, cleanup
RUN set -eux \
 # Install system dependencies required for PHP and Laravel
 && apt-get update \
 && apt-get install -y --no-install-recommends \
    git unzip curl ca-certificates \
    libzip-dev libpng-dev libonig-dev libxml2-dev \
    libpq-dev pkg-config gnupg bash \
 # Enable required PHP extensions
 && docker-php-ext-configure zip \
 && docker-php-ext-install -j"$(nproc)" \
    pdo pdo_mysql zip bcmath exif pcntl gd \
 # Create non-root user "app"
 && groupadd -g 1000 app \
 && useradd -u 1000 -g 1000 -m -s /bin/bash app \
 # Configure PHP-FPM to run under "app"
 && sed -ri 's/^user = .*/user = app/; s/^group = .*/group = app/' /usr/local/etc/php-fpm.d/www.conf \
 && sed -ri 's@^;?listen.owner = .*@listen.owner = app@; s@^;?listen.group = .*@listen.group = app@; s@^;?listen.mode = .*@listen.mode = 0660@' /usr/local/etc/php-fpm.d/www.conf \
 # Remove APT leftovers
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Composer binary
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# ======================
# Development image
# ======================
FROM base AS dev

# Node.js for frontend tooling
RUN set -eux \
 && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get update \
 && apt-get install -y --no-install-recommends nodejs \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Entrypoint for development mode
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

USER 1000:1000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["composer","run","dev"]

# ======================
# Assets build image
# ======================
FROM dev AS assets

WORKDIR /var/www/html
USER 1000:1000

# Install PHP + Node dependencies
COPY --chown=app:app composer.* package*.json ./
RUN set -eux \
 && composer install --no-dev --prefer-dist --no-scripts --no-progress \
 && (npm ci --no-audit --no-fund || npm i)

# Copy code and build frontend
COPY --chown=app:app . .
RUN set -eux \
 && composer dump-autoload --optimize \
 && { php artisan package:discover --ansi || true; } \
 && npm run build

# ======================
# Production image
# ======================
FROM base AS prod

WORKDIR /var/www/html
USER 1000:1000

# Copy application
COPY --chown=app:app . .

# Copy vendors from assets stage
COPY --from=assets --chown=app:app /var/www/html/vendor /var/www/html/vendor

# Laravel runtime setup
RUN set -eux \
 && rm -f public/hot \
 && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && touch storage/logs/laravel.log \
 && chmod -R u+rwX,g+rwX storage bootstrap/cache \
 && composer dump-autoload --optimize \
 && { php artisan package:discover --ansi || true; }

# Copy built assets
COPY --from=assets --chown=app:app /var/www/html/public/build /var/www/html/public/build

# ======================
# Nginx image
# ======================
FROM nginx:alpine AS nginx

# Copy public assets
COPY --from=prod /var/www/html/public /var/www/html/public

# Nginx config
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

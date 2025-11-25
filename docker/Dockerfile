# ======================
# PHP-FPM base image
# ======================
FROM php:8.2-fpm AS base

WORKDIR /var/www/html

# System deps, PHP extensions, Composer, app user
RUN set -eux \
 # Install required system packages
 && apt-get update \
 && apt-get install -y --no-install-recommends \
    git \
    unzip \
    curl \
    ca-certificates \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libsqlite3-dev \
    pkg-config \
    gnupg \
    bash \
 # Configure + install PHP extensions
 && docker-php-ext-configure zip \
 && docker-php-ext-install -j"$(nproc)" \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    zip \
    bcmath \
    exif \
    pcntl \
    gd \
 # Install Composer globally
 && curl -sS https://getcomposer.org/installer \
      | php -- --install-dir=/usr/local/bin --filename=composer \
 # Create non-root application user
 && groupadd -g 1000 app \
 && useradd -u 1000 -g 1000 -m -s /bin/bash app \
 # Update php-fpm pool to run as "app" user
 && sed -ri 's/^user = .*/user = app/; s/^group = .*/group = app/' /usr/local/etc/php-fpm.d/www.conf \
 && sed -ri 's@^;?listen.owner = .*@listen.owner = app@; s@^;?listen.group = .*@listen.group = app@; s@^;?listen.mode = .*@listen.mode = 0660@' /usr/local/etc/php-fpm.d/www.conf \
 # Cleanup to reduce image size
 && rm -rf /var/lib/apt/lists/*

# ======================
# Development image
# ======================
FROM base AS dev

# Node.js
RUN set -eux \
 && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get update \
 && apt-get install -y --no-install-recommends nodejs \
 && rm -rf /var/lib/apt/lists/*

# Dev entrypoint
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

# PHP deps
COPY --chown=app:app composer.* ./
RUN set -eux \
 && composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress

# Node deps
COPY --chown=app:app package*.json ./
RUN set -eux \
 && (npm ci --no-audit --no-fund || npm i)

# App code
COPY --chown=app:app . .

# Build
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

# PHP deps
COPY --chown=app:app composer.* ./
RUN set -eux \
 && composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress

# App code
COPY --chown=app:app . .

# Laravel setup
RUN set -eux \
 && rm -f public/hot \
 && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && touch storage/logs/laravel.log \
 && chmod -R u+rwX,g+rwX storage bootstrap/cache \
 && composer dump-autoload --optimize \
 && { php artisan package:discover --ansi || true; }

# Built frontend assets from assets stage
COPY --from=assets /var/www/html/public/build /var/www/html/public/build

# ======================
# Nginx web server
# ======================
FROM nginx:alpine AS nginx

# Serve Laravel public/ via Nginx & Nginx config
COPY --from=prod /var/www/html/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

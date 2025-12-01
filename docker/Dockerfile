# ======================
# PHP-FPM base image
# ======================
FROM php:8.2-fpm AS base

WORKDIR /var/www/html

# Composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# System deps, PHP extensions, app user
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
 # Create non-root application user
 && groupadd -g 1000 app \
 && useradd -u 1000 -g 1000 -m -s /bin/bash app \
 # Update php-fpm pool to run as "app" user
 && sed -ri 's/^user = .*/user = app/; s/^group = .*/group = app/' /usr/local/etc/php-fpm.d/www.conf \
 && sed -ri 's@^;?listen.owner = .*@listen.owner = app@; s@^;?listen.group = .*@listen.group = app@; s@^;?listen.mode = .*@listen.mode = 0660@' /usr/local/etc/php-fpm.d/www.conf \
 # Cleanup to reduce image size
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# ======================
# Development image
# ======================
FROM base AS dev

# Node.js
RUN set -eux \
 && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get update \
 && apt-get install -y --no-install-recommends nodejs \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Dev entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN set -eux && chmod +x /entrypoint.sh

USER 1000:1000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["composer","run","dev"]

# ======================
# Assets build image
# ======================
FROM dev AS assets

WORKDIR /var/www/html
USER 1000:1000

# PHP and Node deps
COPY --chown=app:app composer.* package*.json ./
RUN set -eux \
 && composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress \
 && composer dump-autoload --optimize \
 && (npm ci --no-audit --no-fund || npm i) \
 && npm cache clean --force >/dev/null 2>&1 || true

# App code and build
COPY --chown=app:app . .
RUN set -eux \
 && { php artisan package:discover --ansi || true; } \
 && npm run build

# ======================
# Production image
# ======================
FROM base AS prod

WORKDIR /var/www/html
USER 1000:1000

# App code
COPY --chown=app:app . .

# Vendor from assets stage
COPY --from=assets --chown=app:app /var/www/html/vendor /var/www/html/vendor

# Laravel setup
RUN set -eux \
 && rm -f public/hot \
 && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && touch storage/logs/laravel.log \
 && chmod -R u+rwX,g+rwX storage bootstrap/cache \
 && composer dump-autoload --optimize \
 && { php artisan package:discover --ansi || true; }

# Built frontend assets from assets stage
COPY --from=assets --chown=app:app /var/www/html/public/build /var/www/html/public/build

# ======================
# Nginx web server
# ======================
FROM nginx:alpine AS nginx

# Serve Laravel public/ via Nginx & Nginx config
COPY --from=prod /var/www/html/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

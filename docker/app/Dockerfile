# =============================================================================
# Philippine Accounting System (PHA) — App Image
# =============================================================================
# FrankenPHP (Caddy + PHP 8.3 in worker mode) for Laravel Octane.
# Builds a single-process container running Laravel via Octane workers.
# =============================================================================

ARG PHP_VERSION=8.3

FROM dunglas/frankenphp:1-php${PHP_VERSION}-alpine AS base

ARG WWWUSER=1000
ARG WWWGROUP=1000

ENV PHP_INI_DIR=/usr/local/etc/php
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_NO_INTERACTION=1

# -----------------------------------------------------------------------------
# System packages
# -----------------------------------------------------------------------------
RUN apk add --no-cache \
        bash \
        curl \
        git \
        icu-dev \
        libpq-dev \
        libzip-dev \
        oniguruma-dev \
        gmp-dev \
        postgresql-client \
        redis \
        supervisor \
        unzip \
        nodejs \
        npm \
    && rm -rf /var/cache/apk/*

# -----------------------------------------------------------------------------
# PHP extensions (required by Laravel + the package set in composer.json)
# -----------------------------------------------------------------------------
RUN install-php-extensions \
        bcmath \
        exif \
        gd \
        gmp \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        redis \
        sodium \
        zip

# -----------------------------------------------------------------------------
# Composer
# -----------------------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# -----------------------------------------------------------------------------
# PHP configuration — tuned for Octane worker mode
# -----------------------------------------------------------------------------
COPY docker/app/php.ini ${PHP_INI_DIR}/conf.d/zz-pha.ini

# -----------------------------------------------------------------------------
# Caddyfile (FrankenPHP routes everything to Octane)
# -----------------------------------------------------------------------------
COPY docker/app/Caddyfile /etc/caddy/Caddyfile

# -----------------------------------------------------------------------------
# Application
# -----------------------------------------------------------------------------
WORKDIR /app

# Copy composer files first for layer caching
COPY composer.json composer.lock* ./
RUN if [ -f composer.lock ]; then \
        composer install --no-dev --no-scripts --no-autoloader --prefer-dist; \
    fi

# Now copy the rest of the code
COPY . .

RUN if [ -f composer.lock ]; then \
        composer dump-autoload --optimize; \
    fi

# Permissions
RUN addgroup -g ${WWWGROUP} -S app && \
    adduser -u ${WWWUSER} -S -G app app && \
    mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache && \
    chown -R app:app /app

EXPOSE 8000

# -----------------------------------------------------------------------------
# Entrypoint — boots FrankenPHP with Octane
# -----------------------------------------------------------------------------
USER app

CMD ["php", "artisan", "octane:frankenphp", \
     "--host=0.0.0.0", "--port=8000", \
     "--workers=4", "--max-requests=500"]

HEALTHCHECK --interval=30s --timeout=5s --retries=3 --start-period=30s \
    CMD curl -fsS http://localhost:8000/up || exit 1

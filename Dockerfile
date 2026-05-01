# syntax=docker/dockerfile:1.7

# ---------- Stage 1: Composer install ----------
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist

# Copy the rest of the source so the autoloader can be generated
COPY . .

# Ensure Laravel's writable directories exist before any artisan command runs
# (they may be empty in git and stripped from the build context).
RUN mkdir -p \
        bootstrap/cache \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/testing \
        storage/logs \
    && chmod -R ug+rwX bootstrap/cache storage

RUN composer dump-autoload --optimize --no-dev


# ---------- Stage 2: Runtime ----------
FROM php:8.3-fpm-alpine AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    COMPOSER_ALLOW_SUPERUSER=1

RUN set -eux; \
    apk add --no-cache \
        nginx \
        supervisor \
        mariadb-client \
        bash \
        tzdata \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        autoconf \
        g++ \
        make; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        intl \
        zip \
        gd \
        opcache \
        exif; \
    apk del .build-deps; \
    mkdir -p /run/nginx /var/log/supervisor

# PHP / FPM / nginx / supervisord configs (baked from /build)
COPY build/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY build/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY build/nginx/default.conf /etc/nginx/http.d/default.conf
COPY build/supervisord.conf /etc/supervisord.conf
COPY build/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Application
WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html

RUN set -eux; \
    chown -R www-data:www-data /var/www/html; \
    find /var/www/html/storage -type d -exec chmod 775 {} +; \
    find /var/www/html/bootstrap/cache -type d -exec chmod 775 {} +

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD wget -qO- http://127.0.0.1/healthz >/dev/null 2>&1 || exit 1

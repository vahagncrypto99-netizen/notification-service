# syntax=docker/dockerfile:1

# ----------------------------
# Composer (только бинарник)
# ----------------------------
FROM composer:2 AS composer

# ----------------------------
# PHP-FPM runtime
# ----------------------------
FROM php:8.3-fpm-alpine

ARG TZ=UTC
ARG UID=1000
ARG GID=1000

# Системные пакеты — только необходимое:
# bash — шелл для make exec.
RUN set -eux \
    && apk add --no-cache bash \
    && apk add --no-cache --virtual .tz tzdata \
    && cp /usr/share/zoneinfo/${TZ} /etc/localtime \
    && echo "${TZ}" > /etc/timezone \
    && apk del .tz

# PHP-расширения — ровно те, что нужны сервису:
# pdo_pgsql — Postgres; pcntl — сигналы/таймауты queue:work;
# sockets — php-amqplib (RabbitMQ); redis — кэш/rate limiting;
# opcache — производительность; zip — composer-дистрибутивы.
RUN set -eux \
    && apk add --no-cache libpq libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers postgresql-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pcntl opcache sockets zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /usr/src/php* /tmp/pear

# Продакшн-базовый php.ini; локальные переопределения монтируются compose-ом
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Непривилегированный пользователь с UID/GID хоста —
# файлы bind-mount не становятся root-овыми, контейнер не работает под root
RUN set -eux \
    && apk add --no-cache --virtual .shadow shadow \
    && usermod -u ${UID} www-data \
    && groupmod -g ${GID} www-data \
    && mkdir -p /home/www-data \
    && chown -R www-data:www-data /home/www-data /var/www \
    && apk del .shadow

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

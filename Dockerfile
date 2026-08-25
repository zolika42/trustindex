FROM php:8.2-cli-alpine

RUN apk add --no-cache \
        curl \
        icu-dev \
        libzip-dev \
        sqlite-dev \
    && docker-php-ext-install intl pdo_sqlite zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts

COPY . .
RUN composer dump-autoload --classmap-authoritative \
    && chmod +x docker/entrypoint.sh \
    && mkdir -p var

ENV APP_ENV=dev
ENV APP_DEBUG=1
ENV DATABASE_URL=sqlite:////app/var/demo.db

EXPOSE 8000

HEALTHCHECK --interval=10s --timeout=3s --retries=5 \
    CMD curl --fail http://127.0.0.1:8000/ >/dev/null || exit 1

ENTRYPOINT ["docker/entrypoint.sh"]

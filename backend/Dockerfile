FROM php:8.4-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libonig-dev bash \
    && docker-php-ext-install pdo pdo_pgsql mbstring bcmath \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

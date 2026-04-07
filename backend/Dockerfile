FROM public.ecr.aws/docker/library/php:8.4-fpm-alpine

RUN apk add --no-cache postgresql-dev oniguruma-dev bash \
    && docker-php-ext-install pdo pdo_pgsql mbstring bcmath

WORKDIR /var/www/html

FROM php:8.5-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        default-mysql-client \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        pdo_mysql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY ./.docker/php/custom.ini /usr/local/etc/php/conf.d/99-custom.ini

WORKDIR /var/www/html

CMD ["sh", "-lc", "if [ -d /var/www/html/storage ] && [ -d /var/www/html/bootstrap/cache ]; then chmod -R 0777 /var/www/html/storage /var/www/html/bootstrap/cache || true; fi; exec php-fpm"]

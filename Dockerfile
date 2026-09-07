FROM php:8.5-apache

ENV TZ=America/Argentina/Buenos_Aires
ENV APACHE_LOG_DIR=/var/log/apache2

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) curl mbstring intl mysqli pdo pdo_mysql exif gd \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY apache/astro.conf /etc/apache2/conf-available/astro.conf
RUN a2enconf astro

WORKDIR /var/www/html

FROM php:8.5-fpm

RUN apt-get update \
	&& apt-get install -y --no-install-recommends git unzip \
	&& rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html
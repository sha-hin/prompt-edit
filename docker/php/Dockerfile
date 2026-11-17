# Development image: PHP 8.4 + Composer + Node 22 in one place, because the
# Wayfinder Vite plugin shells out to `php artisan` while Vite runs.
FROM php:8.4-cli-bookworm

COPY --from=mlocati/php-extension-installer:2.7 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql pcntl redis intl zip bcmath pcov \
    && apt-get update \
    && apt-get install -y --no-install-recommends git unzip default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

COPY php.ini /usr/local/etc/php/conf.d/zz-dev.ini

WORKDIR /var/www/prompt-edit

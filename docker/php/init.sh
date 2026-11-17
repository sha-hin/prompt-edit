#!/bin/sh
# One-off setup run by the `init` service before app, queue and vite start.
# Safe to rerun: every step is a no-op when already done.
set -e

# `artisan serve` drops most env vars before starting PHP's web server, so the
# Docker DB settings must live in .env rather than only in compose.yaml.
if [ ! -f .env ]; then
    cp .env.example .env
    sed -i \
        -e 's/^DB_HOST=.*/DB_HOST=mysql/' \
        -e 's/^DB_PASSWORD=.*/DB_PASSWORD=secret/' \
        .env
fi

composer install --no-interaction --prefer-dist --no-progress

grep -q '^APP_KEY=base64:' .env || php artisan key:generate --ansi

npm install --no-audit --no-fund

php artisan migrate --force --ansi

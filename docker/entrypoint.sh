#!/bin/sh
set -e

cd /var/www/shopmade

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ "${SKIP_SETUP:-0}" != "1" ]; then
    # The production image ships vendor/ and the Vite build; the bind-mounted dev setup installs them here.
    if [ "${SHOPMADE_IMAGE:-}" != "production" ]; then
        if [ "${APP_ENV:-production}" = "production" ]; then
            composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
        else
            composer install --no-interaction --prefer-dist
        fi
    fi

    if [ -z "${APP_KEY:-}" ] && ! grep -qE '^APP_KEY=base64:' .env; then
        php artisan key:generate --force
    fi

    mkdir -p \
        storage/app \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache
    chmod -R ug+rwx storage bootstrap/cache || true

    # A new, empty volume on fronts/ hides the demo site baked into the image: copy it in on first boot.
    if [ -d /opt/shopmade-fronts ] && [ -z "$(ls -A fronts 2>/dev/null)" ]; then
        mkdir -p fronts
        cp -a /opt/shopmade-fronts/. fronts/
    fi

    if [ ! -f public/build/manifest.json ]; then
        npm ci --no-audit --no-fund
        npm run build
    fi

    php artisan migrate --force

    if [ "${SEED_DEMO:-0}" = "1" ]; then
        php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (\App\Models\User::query()->count() === 0) {
    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
    echo \Illuminate\Support\Facades\Artisan::output();
}
PHP
    fi

    # The production image runs php-fpm as www-data; its volumes (and anything setup just wrote) may be root's.
    if [ "${SHOPMADE_IMAGE:-}" = "production" ]; then
        chown -R www-data:www-data storage bootstrap/cache fronts
    fi
fi

exec "$@"

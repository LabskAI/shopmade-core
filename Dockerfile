FROM php:8.4-fpm-trixie AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        git \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        libzip-dev \
        postgresql-client \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        posix \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Node builds the Tailwind and Alpine bundle (npm run build) when the app container starts.
COPY --from=node:22-trixie /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-trixie /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && git config --system --add safe.directory '*'

WORKDIR /var/www/shopmade

COPY docker/php/zz-www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]

FROM base AS app

EXPOSE 9000

CMD ["php-fpm", "-R"]

FROM base AS worker

CMD ["php", "artisan", "queue:work", "database", "--sleep=1", "--tries=3", "--timeout=300"]

# Self-contained image for a registry or a PaaS (Easypanel, Coolify, …): code, vendor and the Vite build are baked in,
# nginx serves port 8080. Keep storage/ and fronts/ on volumes, and set APP_KEY so a restart keeps sessions valid.
# Run the same image with `php artisan queue:work database` and `php artisan schedule:work` for the worker and scheduler.
FROM base AS production

RUN apt-get update && apt-get install -y --no-install-recommends nginx \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default

COPY docker/php/production.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/nginx/production.conf /etc/nginx/conf.d/shopmade.conf
COPY docker/serve.sh /usr/local/bin/serve.sh

COPY . .

RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader \
    && npm ci --no-audit --no-fund \
    && npm run build \
    && rm -rf node_modules \
    && cp -a fronts /opt/shopmade-fronts \
    && mkdir -p storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache fronts \
    && chmod +x /usr/local/bin/serve.sh

# HTTPS is terminated by the proxy in front (Easypanel, Traefik, Coolify): trust its X-Forwarded-* headers and send the
# session cookie only over HTTPS. Set SESSION_SECURE_COOKIE=false to try the image over plain http://localhost.
ENV APP_ENV=production APP_DEBUG=false SHOPMADE_IMAGE=production TRUSTED_PROXIES=* SESSION_SECURE_COOKIE=true

EXPOSE 8080

CMD ["serve.sh"]

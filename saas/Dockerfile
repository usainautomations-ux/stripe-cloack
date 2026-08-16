# Laravel on FrankenPHP.
#
# FrankenPHP rather than nginx + php-fpm because it is a single process serving
# /app/public directly — one container, no supervisor, no socket wiring. That
# matters on a free tier where every megabyte of RAM and every second of cold
# start is budget.
FROM dunglas/frankenphp:1-php8.3

# pdo_pgsql talks to Postgres; sodium backs the envelope encryption that wraps
# every stored Stripe key. Without sodium the app cannot decrypt a single
# credential, so it is not optional.
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    intl \
    zip \
    opcache \
    sodium

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencies are copied and installed before the application code so that a
# code change does not invalidate the dependency layer and force a full
# reinstall on every deploy.
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev --no-interaction

# Laravel writes here at runtime. The container filesystem is ephemeral, so
# nothing durable may live in these paths — sessions and cache go to Postgres.
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Render's sandbox rejects the capability check FrankenPHP's binary carries by
# default (it ships setcap'd for CAP_NET_BIND_SERVICE, so it can bind ports
# below 1024 while running as root) — the container exits immediately with
# "operation not permitted" on `exec`, before a single line of PHP runs. There
# is no privileged port to bind here: Render assigns $PORT itself, always well
# above 1024, so the capability is dead weight. Stripping it and running as an
# unprivileged user satisfies the sandbox and is the standard non-root pattern
# FrankenPHP's own docs describe for exactly this situation.
ARG APP_USER=appuser
RUN useradd --create-home ${APP_USER} \
    && setcap -r /usr/local/bin/frankenphp \
    && chown -R ${APP_USER}:${APP_USER} /app /config/caddy /data/caddy

USER ${APP_USER}

ENTRYPOINT ["entrypoint.sh"]

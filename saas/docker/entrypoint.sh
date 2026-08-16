#!/bin/sh
#
# Container start-up.
#
# Caching happens here rather than at build time because the configuration
# depends on environment variables that only exist at runtime — baking a cache
# during the image build would freeze in placeholder values.

set -e

# Render assigns a port and expects the app to listen on it. FrankenPHP's
# default Caddyfile reads SERVER_NAME, and a bare ":port" means "listen on that
# port without TLS" — correct here, because the platform terminates TLS in
# front of us.
export SERVER_NAME=":${PORT:-10000}"

echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrations are deliberately NOT run here. On a platform that can start
# several containers, concurrent start-ups would race each other through the
# same migration. Run them manually, once, from your own machine.

echo "Starting FrankenPHP on ${SERVER_NAME}..."
exec frankenphp run --config /etc/caddy/Caddyfile

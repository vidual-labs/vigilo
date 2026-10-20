#!/bin/sh
# vigilo container entrypoint.
#
# VIGILO_ROLE=app    (the php-fpm container) prepares the install on every
#                    start: APP_KEY, database, migrations, first operator,
#                    demo data on first boot.
# VIGILO_ROLE=worker (queue, scheduler) waits for that and starts.
set -e
cd /app

role="${VIGILO_ROLE:-app}"

# The storage volume may predate folders added in newer releases.
mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/framework/testing storage/logs

# 1. APP_KEY: from the environment, or generated once and kept in the volume
#    (config/app.php reads storage/app/.app-key when APP_KEY is empty).
if [ -z "${APP_KEY}" ]; then
    if [ "$role" = "app" ] && [ ! -s storage/app/.app-key ]; then
        umask 077
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > storage/app/.app-key
        echo "vigilo: generated an application key (kept in the storage volume)."
    fi
    tries=0
    until [ -s storage/app/.app-key ]; do
        tries=$((tries + 1))
        if [ "$tries" -gt 90 ]; then
            echo "vigilo: no application key found; is the app container running?" >&2
            exit 1
        fi
        sleep 2
    done
fi

# 2. Database.
php artisan vigilo:db:wait --timeout=120

if [ "$role" = "app" ]; then
    # 3. Schema, first operator (refuses weak passwords), demo data once.
    php artisan migrate --force
    php artisan vigilo:admin:ensure
    php artisan vigilo:demo:seed --first-boot
fi

exec "$@"

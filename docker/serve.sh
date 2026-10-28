#!/bin/sh
# Production image: php-fpm in the background, nginx in the foreground on port 8080. Unless RUN_WORKERS=0, the queue
# worker and the scheduler run here too, so one container is a whole site.
set -e

# Run a command as www-data forever. A run that ends within a minute (the database is down, say) doubles the pause
# before the next one, up to 5 minutes, so a crash loop doesn't flood the logs; a longer run resets it.
keep_running() {
    pause=2
    while true; do
        started=$(date +%s)
        su -s /bin/sh www-data -c "$1" || true
        if [ $(( $(date +%s) - started )) -ge 60 ]; then
            pause=2
        else
            echo "serve.sh: '$1' stopped; restarting in ${pause}s" >&2
            pause=$(( pause * 2 > 300 ? 300 : pause * 2 ))
        fi
        sleep "$pause"
    done
}

if [ "${RUN_WORKERS:-1}" = "1" ]; then
    keep_running 'php artisan queue:work database --sleep=1 --tries=3 --timeout=300 --max-time=3600' &
    keep_running 'php artisan schedule:work' &
fi

php-fpm -D
exec nginx -g 'daemon off;'

#!/bin/sh
# Production image: php-fpm in the background, nginx in the foreground on port 8080. Unless RUN_WORKERS=0, the queue
# worker and the scheduler run here too (restarted if they exit), so one container is a whole site.
set -e

if [ "${RUN_WORKERS:-1}" = "1" ]; then
    (while true; do su -s /bin/sh www-data -c 'php artisan queue:work database --sleep=1 --tries=3 --timeout=300 --max-time=3600'; sleep 2; done) &
    (while true; do su -s /bin/sh www-data -c 'php artisan schedule:work'; sleep 2; done) &
fi

php-fpm -D
exec nginx -g 'daemon off;'

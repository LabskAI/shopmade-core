#!/bin/sh
# Production image: php-fpm in the background, nginx in the foreground on port 8080.
set -e

php-fpm -D
exec nginx -g 'daemon off;'

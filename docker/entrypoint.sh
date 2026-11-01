#!/bin/sh
set -eu
if [ "${DB_CONNECTION:-sqlite}" != sqlite ]; then
    echo 'Paket deployment ini mendukung SQLite. Driver lain memerlukan pengujian terpisah.' >&2
    exit 1
fi
jwt_secret=${JWT_SECRET:-}
if [ "${#jwt_secret}" -lt 32 ]; then
    echo 'JWT_SECRET wajib diisi minimal 32 karakter.' >&2
    exit 1
fi
mkdir -p /var/www/html/storage/database /var/www/html/uploads /var/www/html/tmp
chown -R www-data:www-data /var/www/html/storage /var/www/html/uploads /var/www/html/tmp
# Run initialization as the same user as Apache workers.
runuser -u www-data -- php /var/www/html/docker/init.php
exec docker-php-entrypoint "$@"

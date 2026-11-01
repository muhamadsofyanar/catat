FROM php:8.4-apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev util-linux \
    && docker-php-ext-install pdo_sqlite \
    && a2enmod headers \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY . .
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/legavoice.ini
RUN chmod +x docker/entrypoint.sh \
    && find . -name '*.php' -print0 | xargs -0 -n1 php -l
ENV DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/storage/database/database.sqlite \
    SESSION_SECURE_COOKIE=true
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/public/healthz.php") === "{\"status\":\"ok\"}" ? 0 : 1);'
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["apache2-foreground"]

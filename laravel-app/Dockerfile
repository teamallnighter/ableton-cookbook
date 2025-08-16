FROM serversideup/php:8.4-fpm-nginx

USER root

# Install required PHP extensions
RUN docker-php-ext-install exif

# Install Node.js for building assets
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

USER www-data

# Copy application files
COPY --chown=www-data:www-data . /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Node dependencies and build assets
RUN npm ci && npm run build && rm -rf node_modules

# Create database directory and file during build
USER root
RUN mkdir -p /var/www/html/database \
    && touch /var/www/html/database/database.sqlite \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database \
    && chmod 664 /var/www/html/database/database.sqlite

USER www-data

# Create entrypoint script for Railway
USER root
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/
COPY --chmod=755 docker-entrypoint-simple.sh /usr/local/bin/

USER www-data

EXPOSE 8000

# Use simple entrypoint for debugging
ENTRYPOINT ["/usr/local/bin/docker-entrypoint-simple.sh"]
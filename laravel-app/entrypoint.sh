#!/bin/bash

# Create database directory and file if they don't exist
mkdir -p /var/www/html/database
if [ ! -f /var/www/html/database/database.sqlite ]; then
    echo "Creating SQLite database file..."
    touch /var/www/html/database/database.sqlite
    chmod 664 /var/www/html/database/database.sqlite
    chown www-data:www-data /var/www/html/database/database.sqlite
fi

# Ensure proper permissions
chown -R www-data:www-data /var/www/html/database
chmod -R 775 /var/www/html/database

# Run the original entrypoint
exec /usr/local/bin/docker-php-entrypoint "$@"
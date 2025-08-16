#!/bin/bash

# Wait for database to be ready (if using external DB)
# For SQLite, ensure database file exists
if [ ! -f /var/www/html/database/database.sqlite ]; then
    echo "Creating SQLite database..."
    touch /var/www/html/database/database.sqlite
    chmod 664 /var/www/html/database/database.sqlite
fi

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

# Start the web server
exec "$@"
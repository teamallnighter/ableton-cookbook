#!/bin/bash
set -e

echo "=== Simple Railway startup for debugging ==="

# Set default port
PORT=${PORT:-8000}

# Create minimal .env file
if [ ! -f /var/www/html/.env ]; then
    echo "Creating .env file..."
    cat > /var/www/html/.env << EOF
APP_NAME="Ableton Cookbook"
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite
LOG_CHANNEL=stderr
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=private
EOF
fi

# Create database
mkdir -p /var/www/html/database
if [ ! -f /var/www/html/database/database.sqlite ]; then
    touch /var/www/html/database/database.sqlite
    chmod 664 /var/www/html/database/database.sqlite
fi

# Basic Laravel check
echo "Testing Laravel..."
php artisan --version || exit 1

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo "Starting simple PHP server on port $PORT..."
exec php artisan serve --host=0.0.0.0 --port=$PORT --no-reload
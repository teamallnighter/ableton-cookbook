#!/bin/bash
set -e

echo "Starting Railway deployment..."

# Set default port if not provided
PORT=${PORT:-8000}

# Create minimal .env file if it doesn't exist (Railway provides env vars directly)
if [ ! -f /var/www/html/.env ]; then
    echo "Creating minimal .env file for Laravel..."
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

# Ensure database directory exists
mkdir -p /var/www/html/database

# For SQLite, ensure database file exists
if [ ! -f /var/www/html/database/database.sqlite ]; then
    echo "Creating SQLite database..."
    touch /var/www/html/database/database.sqlite
    chmod 664 /var/www/html/database/database.sqlite
fi

# Generate application key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Clear Laravel caches (for fresh deployments)
echo "Clearing Laravel caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run migrations
echo "Running database migrations..."
php artisan migrate --force || {
    echo "Migration failed, retrying after 5 seconds..."
    sleep 5
    php artisan migrate --force
}

# Cache configuration for production
echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link if it doesn't exist
if [ ! -L /var/www/html/public/storage ]; then
    echo "Creating storage link..."
    php artisan storage:link
fi

# Update Nginx configuration to use Railway's port
sed -i "s/listen 8000;/listen $PORT;/" /etc/nginx/sites-available/default
sed -i "s/listen \[::\]:8000;/listen [::]:$PORT;/" /etc/nginx/sites-available/default

# Test if the app can bootstrap
echo "Testing Laravel bootstrap..."
php artisan --version || {
    echo "Laravel bootstrap failed!"
    exit 1
}

echo "Environment check:"
echo "APP_ENV: $APP_ENV"
echo "APP_KEY: ${APP_KEY:0:20}..."
echo "DB_CONNECTION: $DB_CONNECTION"
echo "PORT: $PORT"

# Start PHP-FPM in background
echo "Starting PHP-FPM..."
php-fpm8.4 -D

# Start Nginx in foreground
echo "Starting Nginx on port $PORT..."
exec nginx -g "daemon off;"
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
DB_CONNECTION=pgsql
LOG_CHANNEL=stderr
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=private
REDIS_CLIENT=phpredis
EOF
fi

# Wait for database to be ready (PostgreSQL)
echo "Waiting for database connection..."
until php artisan tinker --execute="DB::connection()->getPdo(); echo 'DB Connected';" 2>/dev/null; do
    echo "Database not ready, waiting 2 seconds..."
    sleep 2
done
echo "Database connected successfully!"

# Debug environment variables
echo "=== Environment Debug ==="
echo "DATABASE_URL: ${DATABASE_URL:0:50}..."
echo "DB_CONNECTION: $DB_CONNECTION"
echo "DB_HOST: $DB_HOST"
echo "DB_PORT: $DB_PORT"
echo "DB_DATABASE: $DB_DATABASE"

# Basic Laravel check
echo "Testing Laravel..."
php artisan --version || exit 1

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run migrations with conflict resolution
echo "Running database migrations..."
if ! php artisan migrate --force; then
    echo "Migration failed due to conflicts. Performing fresh migration..."
    
    # Drop all tables and start fresh
    php artisan migrate:fresh --force
    
    echo "Fresh migration completed successfully!"
fi

echo "Starting simple PHP server on port $PORT..."
exec php artisan serve --host=0.0.0.0 --port=$PORT --no-reload
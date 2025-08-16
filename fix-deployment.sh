#!/bin/bash

# Fix deployment - run this on your Ubuntu server

cd /var/www/ableton-cookbook

# Check current structure
echo "Current structure:"
ls -la

# Check if laravel-app exists in repo
if [ -d "laravel-app" ]; then
    echo "Found laravel-app directory, moving contents..."
    cp -r laravel-app/* . 2>/dev/null || true
    cp -r laravel-app/.[^.]* . 2>/dev/null || true
else
    echo "No laravel-app directory found. Checking if files are already in place..."
fi

# Verify we have the Laravel files
if [ ! -f "artisan" ]; then
    echo "ERROR: Laravel files not found! Let's re-clone..."
    cd /var/www
    rm -rf ableton-cookbook
    git clone https://github.com/teamallnighter/ableton-cookbook.git
    cd ableton-cookbook
    
    # Now copy Laravel files from subdirectory
    if [ -d "laravel-app" ]; then
        cp -r laravel-app/* .
        cp -r laravel-app/.[^.]* . 2>/dev/null || true
    fi
fi

# Continue with setup
echo "Installing dependencies..."
composer install --no-dev --optimize-autoloader

# Create .env if it doesn't exist
if [ ! -f ".env" ]; then
    echo "Creating .env file..."
    cat > .env << 'EOF'
APP_NAME="Ableton Cookbook"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://209.74.83.240

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/ableton-cookbook/database/database.sqlite

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAX_UPLOAD_SIZE=20M
EOF
fi

# Create database
echo "Setting up database..."
mkdir -p database
touch database/database.sqlite
chmod 664 database/database.sqlite

# Generate key if needed
if ! grep -q "APP_KEY=base64:" .env; then
    php artisan key:generate
fi

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Optimize
echo "Optimizing..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Fix permissions
echo "Setting permissions..."
chown -R www-data:www-data /var/www/ableton-cookbook
chmod -R 755 /var/www/ableton-cookbook
chmod -R 775 storage bootstrap/cache database

# Restart services
echo "Restarting services..."
systemctl restart php8.2-fpm
systemctl restart nginx

echo "Done! Checking status..."
echo ""
echo "Files in /var/www/ableton-cookbook:"
ls -la /var/www/ableton-cookbook/ | head -10
echo ""
echo "Your app should now be running at: http://209.74.83.240"
#!/bin/bash

# Upload Laravel app to Ubuntu server

SERVER_IP="209.74.83.240"
SERVER_PORT="22022"
SERVER_USER="root"
APP_DIR="/var/www/ableton-cookbook"

echo "Uploading Laravel app to server..."

# Create a deployment package excluding unnecessary files
cd laravel-app
tar czf ../deployment.tar.gz \
    --exclude=node_modules \
    --exclude=.git \
    --exclude=tests \
    --exclude=.env \
    --exclude=storage/logs/* \
    --exclude=storage/framework/cache/* \
    --exclude=storage/framework/sessions/* \
    --exclude=storage/framework/views/* \
    .

cd ..

# Upload to server using scp
echo "Uploading deployment package..."
sshpass -p 'Trustno1!' scp -P $SERVER_PORT deployment.tar.gz $SERVER_USER@$SERVER_IP:/tmp/

# Extract on server and set up
sshpass -p 'Trustno1!' ssh -p $SERVER_PORT $SERVER_USER@$SERVER_IP << 'ENDSSH'
    # Create app directory
    mkdir -p /var/www/ableton-cookbook
    cd /var/www/ableton-cookbook
    
    # Extract the application
    tar xzf /tmp/deployment.tar.gz
    rm /tmp/deployment.tar.gz
    
    # Install dependencies
    composer install --no-dev --optimize-autoloader
    
    # Create .env file for production
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

MEMCACHED_HOST=127.0.0.1
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
EOF
    
    # Create SQLite database
    touch database/database.sqlite
    
    # Generate application key
    php artisan key:generate
    
    # Run migrations
    php artisan migrate --force
    
    # Optimize for production
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    
    # Set permissions
    chown -R www-data:www-data /var/www/ableton-cookbook
    chmod -R 755 /var/www/ableton-cookbook
    chmod -R 775 storage bootstrap/cache
    
    # Restart services
    systemctl restart php8.2-fpm
    systemctl restart nginx
    
    echo "Deployment complete!"
    echo "Your app should be accessible at http://209.74.83.240"
ENDSSH

# Clean up
rm -f deployment.tar.gz

echo "Upload and deployment complete!"
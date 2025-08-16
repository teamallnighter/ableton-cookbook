#!/bin/bash

# Ableton Cookbook - GitHub Deployment Script for Ubuntu
# Run this as root on your Ubuntu server

set -e

echo "🎵 Starting Ableton Cookbook deployment from GitHub..."

# Color codes
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Update system
echo -e "${GREEN}Updating system packages...${NC}"
apt update && apt upgrade -y
apt install -y curl wget git unzip software-properties-common

# Install PHP 8.2
echo -e "${GREEN}Installing PHP 8.2...${NC}"
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y \
    php8.2 \
    php8.2-fpm \
    php8.2-cli \
    php8.2-common \
    php8.2-sqlite3 \
    php8.2-zip \
    php8.2-gd \
    php8.2-mbstring \
    php8.2-curl \
    php8.2-xml \
    php8.2-bcmath \
    php8.2-intl \
    php8.2-exif \
    php8.2-opcache

# Configure PHP for production
echo -e "${GREEN}Configuring PHP...${NC}"
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 20M/' /etc/php/8.2/fpm/php.ini
sed -i 's/post_max_size = 8M/post_max_size = 25M/' /etc/php/8.2/fpm/php.ini
sed -i 's/memory_limit = 128M/memory_limit = 256M/' /etc/php/8.2/fpm/php.ini

# Install Composer
echo -e "${GREEN}Installing Composer...${NC}"
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# Install Nginx
echo -e "${GREEN}Installing Nginx...${NC}"
apt install -y nginx

# Clone repository from GitHub
echo -e "${GREEN}Cloning repository from GitHub...${NC}"
cd /var/www
rm -rf ableton-cookbook
git clone https://github.com/teamallnighter/ableton-cookbook.git
cd ableton-cookbook

# Move Laravel app to root if it's in a subdirectory
if [ -d "laravel-app" ]; then
    echo -e "${GREEN}Moving Laravel app to root directory...${NC}"
    mv laravel-app/* .
    mv laravel-app/.[^.]* . 2>/dev/null || true
    rmdir laravel-app
fi

# Install Composer dependencies
echo -e "${GREEN}Installing Composer dependencies...${NC}"
composer install --no-dev --optimize-autoloader

# Create .env file
echo -e "${GREEN}Creating .env file...${NC}"
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

# File upload settings
MAX_UPLOAD_SIZE=20M
EOF

# Create SQLite database
echo -e "${GREEN}Setting up SQLite database...${NC}"
mkdir -p database
touch database/database.sqlite
chmod 664 database/database.sqlite

# Generate application key
echo -e "${GREEN}Generating application key...${NC}"
php artisan key:generate

# Run migrations and seeders
echo -e "${GREEN}Running database migrations...${NC}"
php artisan migrate --force

# Optimize for production
echo -e "${GREEN}Optimizing for production...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Configure Nginx
echo -e "${GREEN}Configuring Nginx...${NC}"
cat > /etc/nginx/sites-available/ableton-cookbook << 'NGINX'
server {
    listen 80;
    listen [::]:80;
    server_name _;
    
    root /var/www/ableton-cookbook/public;
    index index.php index.html;
    
    client_max_body_size 25M;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss application/rss+xml application/atom+xml image/svg+xml text/x-js text/x-cross-domain-policy application/x-font-ttf application/x-font-opentype application/vnd.ms-fontobject image/x-icon;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
    
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
NGINX

# Enable site and remove default
ln -sf /etc/nginx/sites-available/ableton-cookbook /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test nginx configuration
nginx -t

# Set permissions
echo -e "${GREEN}Setting permissions...${NC}"
chown -R www-data:www-data /var/www/ableton-cookbook
chmod -R 755 /var/www/ableton-cookbook
chmod -R 775 /var/www/ableton-cookbook/storage
chmod -R 775 /var/www/ableton-cookbook/bootstrap/cache
chmod 775 /var/www/ableton-cookbook/database
chmod 664 /var/www/ableton-cookbook/database/database.sqlite

# Configure firewall
echo -e "${GREEN}Configuring firewall...${NC}"
ufw allow 22022/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# Restart services
echo -e "${GREEN}Starting services...${NC}"
systemctl restart php8.2-fpm
systemctl restart nginx
systemctl enable php8.2-fpm
systemctl enable nginx

# Create update script for future deployments
echo -e "${GREEN}Creating update script for future deployments...${NC}"
cat > /root/update-ableton-cookbook.sh << 'UPDATE'
#!/bin/bash
cd /var/www/ableton-cookbook
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
chown -R www-data:www-data /var/www/ableton-cookbook
systemctl restart php8.2-fpm
echo "Update complete!"
UPDATE
chmod +x /root/update-ableton-cookbook.sh

echo ""
echo -e "${GREEN}✅ Deployment complete!${NC}"
echo ""
echo -e "${YELLOW}Your application is now available at:${NC}"
echo "http://209.74.83.240"
echo ""
echo -e "${YELLOW}To update in the future, run:${NC}"
echo "/root/update-ableton-cookbook.sh"
echo ""
echo -e "${YELLOW}To add SSL with Let's Encrypt:${NC}"
echo "apt install -y certbot python3-certbot-nginx"
echo "certbot --nginx -d yourdomain.com"
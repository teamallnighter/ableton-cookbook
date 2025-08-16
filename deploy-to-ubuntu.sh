#!/bin/bash

# Ableton Cookbook - Simple Ubuntu Deployment Script
# Run this on your Ubuntu server as root

set -e

echo "Starting Ableton Cookbook deployment..."

# Update system
apt update && apt upgrade -y
apt install -y curl wget gnupg2 software-properties-common apt-transport-https ca-certificates unzip git

# Install PHP 8.2
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
    php8.2-exif

# Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# Install Nginx
apt install -y nginx

# Create app directory
mkdir -p /var/www/ableton-cookbook
cd /var/www/ableton-cookbook

# Clone the repository (you'll need to upload your code)
echo "Please upload your Laravel app to /var/www/ableton-cookbook"
echo "You can use scp, rsync, or git clone"

# Set up Nginx configuration
cat > /etc/nginx/sites-available/ableton-cookbook << 'EOF'
server {
    listen 80;
    server_name _;
    
    root /var/www/ableton-cookbook/public;
    index index.php index.html;
    
    client_max_body_size 25M;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# Enable the site
ln -sf /etc/nginx/sites-available/ableton-cookbook /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Set permissions
chown -R www-data:www-data /var/www/ableton-cookbook
chmod -R 755 /var/www/ableton-cookbook

# Restart services
systemctl restart php8.2-fpm
systemctl restart nginx
systemctl enable php8.2-fpm
systemctl enable nginx

# Configure firewall
ufw allow 22022/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo "Basic setup complete!"
echo ""
echo "Next steps:"
echo "1. Upload your Laravel app to /var/www/ableton-cookbook"
echo "2. Run: cd /var/www/ableton-cookbook && composer install --no-dev"
echo "3. Copy .env file and configure it"
echo "4. Run: php artisan key:generate"
echo "5. Run: php artisan migrate"
echo "6. Set permissions: chown -R www-data:www-data storage bootstrap/cache"
echo "7. Your app should be accessible at http://209.74.83.240"
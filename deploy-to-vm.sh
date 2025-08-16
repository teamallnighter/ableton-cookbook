#!/bin/bash

# Ableton Cookbook - Complete Ubuntu 22.04 Deployment Script
# Run this script on your fresh Ubuntu 22.04 VM

set -e  # Exit on any error

echo "🚀 Starting Ableton Cookbook deployment on Ubuntu 22.04..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "Please run this script as root (sudo)"
    exit 1
fi

# Update system
print_status "Updating system packages..."
apt update && apt upgrade -y

# Install essential packages
print_status "Installing essential packages..."
apt install -y software-properties-common curl wget gnupg2 ca-certificates lsb-release apt-transport-https

# Add PHP 8.2 repository
print_status "Adding PHP 8.2 repository..."
add-apt-repository ppa:ondrej/php -y
apt update

# Install PHP 8.2 and extensions
print_status "Installing PHP 8.2 and extensions..."
apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-mysql php8.2-sqlite3 \
    php8.2-redis php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip \
    php8.2-gd php8.2-intl php8.2-bcmath php8.2-dom php8.2-fileinfo

# Install Nginx
print_status "Installing Nginx..."
apt install -y nginx

# Install MySQL
print_status "Installing MySQL Server..."
apt install -y mysql-server

# Install Redis
print_status "Installing Redis..."
apt install -y redis-server

# Install Node.js 20
print_status "Installing Node.js 20..."
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# Install Composer
print_status "Installing Composer..."
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# Install Supervisor for process management
print_status "Installing Supervisor..."
apt install -y supervisor

# Install UFW firewall
print_status "Installing UFW firewall..."
apt install -y ufw

# Configure firewall
print_status "Configuring firewall..."
ufw allow ssh
ufw allow 'Nginx Full'
ufw --force enable

# Create application directory
print_status "Creating application directory..."
mkdir -p /var/www/ableton-cookbook
chown $SUDO_USER:www-data /var/www/ableton-cookbook

# Configure PHP-FPM
print_status "Configuring PHP-FPM..."
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 50M/' /etc/php/8.2/fpm/php.ini
sed -i 's/post_max_size = 8M/post_max_size = 50M/' /etc/php/8.2/fpm/php.ini
sed -i 's/max_execution_time = 30/max_execution_time = 300/' /etc/php/8.2/fpm/php.ini
sed -i 's/memory_limit = 128M/memory_limit = 512M/' /etc/php/8.2/fpm/php.ini

# Restart services
print_status "Starting and enabling services..."
systemctl enable nginx php8.2-fpm mysql redis-server supervisor
systemctl start nginx php8.2-fpm mysql redis-server supervisor

print_status "✅ System setup complete!"
print_warning "Next steps:"
echo "1. Secure MySQL: mysql_secure_installation"
echo "2. Create database and user"
echo "3. Clone your repository to /var/www/ableton-cookbook"
echo "4. Configure application environment"
echo "5. Set up Nginx virtual host"
echo "6. Configure SSL with Let's Encrypt"

print_status "🎉 Base system is ready for Ableton Cookbook deployment!"

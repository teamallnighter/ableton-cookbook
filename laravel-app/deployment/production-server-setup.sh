#!/bin/bash

# Ableton Cookbook Laravel Production Server Setup
# Ubuntu 22.04 LTS - Custom SSH Port 22022
# Domain: ableton.recipes (209.74.83.240)

set -e

echo "🎵 Ableton Cookbook Production Server Setup Starting..."

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if script is run as root
check_root() {
    if [[ $EUID -eq 0 ]]; then
        log_error "This script should not be run as root. Run as a regular user with sudo privileges."
        exit 1
    fi
}

# Function to update system packages
update_system() {
    log_info "Updating system packages..."
    sudo apt update && sudo apt upgrade -y
    sudo apt install -y curl wget gnupg2 software-properties-common apt-transport-https ca-certificates
}

# Function to install PHP 8.2 and extensions
install_php() {
    log_info "Installing PHP 8.2 and required extensions..."
    
    # Add Ondrej PHP repository
    sudo add-apt-repository ppa:ondrej/php -y
    sudo apt update
    
    # Install PHP 8.2 and extensions
    sudo apt install -y \
        php8.2 \
        php8.2-fpm \
        php8.2-cli \
        php8.2-common \
        php8.2-mysql \
        php8.2-pgsql \
        php8.2-sqlite3 \
        php8.2-zip \
        php8.2-gd \
        php8.2-mbstring \
        php8.2-curl \
        php8.2-xml \
        php8.2-bcmath \
        php8.2-json \
        php8.2-intl \
        php8.2-redis \
        php8.2-imagick \
        php8.2-opcache
    
    # Configure PHP-FPM
    sudo sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' /etc/php/8.2/fpm/php.ini
    sudo sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 20M/' /etc/php/8.2/fpm/php.ini
    sudo sed -i 's/post_max_size = 8M/post_max_size = 25M/' /etc/php/8.2/fpm/php.ini
    sudo sed -i 's/max_execution_time = 30/max_execution_time = 300/' /etc/php/8.2/fpm/php.ini
    sudo sed -i 's/memory_limit = 128M/memory_limit = 512M/' /etc/php/8.2/fpm/php.ini
    
    # Configure OPcache for production
    sudo tee -a /etc/php/8.2/fpm/conf.d/10-opcache.ini << EOF
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
opcache.validate_timestamps=0
EOF
    
    log_info "PHP 8.2 installed successfully"
}

# Function to install Composer
install_composer() {
    log_info "Installing Composer..."
    
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    sudo chmod +x /usr/local/bin/composer
    
    log_info "Composer installed successfully"
}

# Function to install Node.js and npm
install_nodejs() {
    log_info "Installing Node.js 20 LTS..."
    
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt install -y nodejs
    
    # Install global packages
    sudo npm install -g pm2
    
    log_info "Node.js and npm installed successfully"
}

# Function to install and configure Nginx
install_nginx() {
    log_info "Installing and configuring Nginx..."
    
    sudo apt install -y nginx
    
    # Remove default site
    sudo rm -f /etc/nginx/sites-enabled/default
    
    # Create optimized nginx configuration
    sudo tee /etc/nginx/sites-available/ableton-cookbook << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name ableton.recipes www.ableton.recipes;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ableton.recipes www.ableton.recipes;
    
    root /var/www/ableton-cookbook/public;
    index index.php index.html index.htm;
    
    # SSL Configuration (will be configured by Certbot)
    # ssl_certificate /etc/letsencrypt/live/ableton.recipes/fullchain.pem;
    # ssl_certificate_key /etc/letsencrypt/live/ableton.recipes/privkey.pem;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private must-revalidate auth;
    gzip_types
        application/atom+xml
        application/javascript
        application/json
        application/ld+json
        application/manifest+json
        application/rss+xml
        application/vnd.geo+json
        application/vnd.ms-fontobject
        application/x-font-ttf
        application/x-web-app-manifest+json
        font/opentype
        image/bmp
        image/svg+xml
        image/x-icon
        text/cache-manifest
        text/css
        text/plain
        text/vcard
        text/vnd.rim.location.xloc
        text/vtt
        text/x-component
        text/x-cross-domain-policy;
    
    # File upload size
    client_max_body_size 25M;
    
    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
    
    # Block access to sensitive files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    location ~ (composer\.(json|lock)|package\.(json|lock)|\.env) {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    # Laravel application
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        
        # Increase timeouts for large file processing
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_connect_timeout 300;
    }
    
    # Deny access to storage and bootstrap/cache
    location ^~ /storage/ {
        deny all;
    }
    
    location ^~ /bootstrap/cache/ {
        deny all;
    }
    
    # SEO-friendly URLs
    location = /sitemap.xml {
        try_files $uri /index.php?$query_string;
    }
    
    location ~ ^/sitemap.*\.xml$ {
        try_files $uri /index.php?$query_string;
    }
}
EOF
    
    # Enable site
    sudo ln -sf /etc/nginx/sites-available/ableton-cookbook /etc/nginx/sites-enabled/
    
    # Test nginx configuration
    sudo nginx -t
    
    log_info "Nginx configured successfully"
}

# Function to install and configure MySQL
install_mysql() {
    log_info "Installing MySQL 8.0..."
    
    sudo apt install -y mysql-server mysql-client
    
    # Secure MySQL installation
    sudo mysql_secure_installation <<EOF

y
2
abletonCookbook2024!
abletonCookbook2024!
y
y
y
y
EOF
    
    # Create database and user
    sudo mysql -e "CREATE DATABASE ableton_cookbook CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    sudo mysql -e "CREATE USER 'ableton_user'@'localhost' IDENTIFIED BY 'abletonCookbook2024!';"
    sudo mysql -e "GRANT ALL PRIVILEGES ON ableton_cookbook.* TO 'ableton_user'@'localhost';"
    sudo mysql -e "FLUSH PRIVILEGES;"
    
    # Optimize MySQL for Laravel
    sudo tee -a /etc/mysql/mysql.conf.d/mysqld.cnf << EOF

# Laravel optimizations
max_connections = 200
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_file_per_table = 1
query_cache_type = 1
query_cache_size = 64M
tmp_table_size = 64M
max_heap_table_size = 64M
EOF
    
    sudo systemctl restart mysql
    
    log_info "MySQL installed and configured successfully"
}

# Function to install and configure Redis
install_redis() {
    log_info "Installing Redis..."
    
    sudo apt install -y redis-server
    
    # Configure Redis for production
    sudo sed -i 's/# maxmemory <bytes>/maxmemory 256mb/' /etc/redis/redis.conf
    sudo sed -i 's/# maxmemory-policy noeviction/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf
    sudo sed -i 's/save 900 1/# save 900 1/' /etc/redis/redis.conf
    sudo sed -i 's/save 300 10/# save 300 10/' /etc/redis/redis.conf
    sudo sed -i 's/save 60 10000/# save 60 10000/' /etc/redis/redis.conf
    
    sudo systemctl restart redis-server
    sudo systemctl enable redis-server
    
    log_info "Redis installed and configured successfully"
}

# Function to configure firewall
configure_firewall() {
    log_info "Configuring UFW firewall..."
    
    sudo ufw --force reset
    sudo ufw default deny incoming
    sudo ufw default allow outgoing
    
    # Allow SSH on custom port
    sudo ufw allow 22022/tcp
    
    # Allow HTTP and HTTPS
    sudo ufw allow 80/tcp
    sudo ufw allow 443/tcp
    
    # Rate limiting for SSH
    sudo ufw limit 22022/tcp
    
    sudo ufw --force enable
    
    log_info "Firewall configured successfully"
}

# Function to create application user
create_app_user() {
    log_info "Creating application user..."
    
    sudo useradd -m -s /bin/bash -d /var/www deploy
    sudo usermod -aG www-data deploy
    
    # Create SSH directory
    sudo mkdir -p /var/www/deploy/.ssh
    sudo chown deploy:deploy /var/www/deploy/.ssh
    sudo chmod 700 /var/www/deploy/.ssh
    
    log_info "Application user 'deploy' created successfully"
}

# Function to create application directory structure
create_app_structure() {
    log_info "Creating application directory structure..."
    
    sudo mkdir -p /var/www/ableton-cookbook
    sudo chown -R deploy:www-data /var/www/ableton-cookbook
    sudo chmod -R 755 /var/www/ableton-cookbook
    
    # Create shared directories for zero-downtime deployments
    sudo -u deploy mkdir -p /var/www/ableton-cookbook/{releases,shared,shared/storage}
    sudo -u deploy mkdir -p /var/www/ableton-cookbook/shared/storage/{app,framework,logs}
    sudo -u deploy mkdir -p /var/www/ableton-cookbook/shared/storage/app/{private,public}
    sudo -u deploy mkdir -p /var/www/ableton-cookbook/shared/storage/framework/{cache,sessions,testing,views}
    
    log_info "Application directory structure created successfully"
}

# Function to configure services
configure_services() {
    log_info "Configuring system services..."
    
    # Enable services
    sudo systemctl enable nginx
    sudo systemctl enable php8.2-fpm
    sudo systemctl enable mysql
    sudo systemctl enable redis-server
    
    # Start services
    sudo systemctl start nginx
    sudo systemctl start php8.2-fpm
    sudo systemctl start mysql
    sudo systemctl start redis-server
    
    log_info "System services configured successfully"
}

# Function to install monitoring tools
install_monitoring() {
    log_info "Installing monitoring tools..."
    
    # Install htop, netstat, and other monitoring tools
    sudo apt install -y htop net-tools iotop fail2ban logrotate
    
    # Configure fail2ban
    sudo tee /etc/fail2ban/jail.local << EOF
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 3

[sshd]
enabled = true
port = 22022
logpath = %(sshd_log)s
backend = %(sshd_backend)s

[nginx-http-auth]
enabled = true
filter = nginx-http-auth
logpath = /var/log/nginx/error.log
maxretry = 3

[nginx-noscript]
enabled = true
logpath = /var/log/nginx/access.log
maxretry = 6

[nginx-badbots]
enabled = true
logpath = /var/log/nginx/access.log
maxretry = 2

[nginx-noproxy]
enabled = true
logpath = /var/log/nginx/access.log
maxretry = 2
EOF
    
    sudo systemctl enable fail2ban
    sudo systemctl start fail2ban
    
    log_info "Monitoring tools installed successfully"
}

# Function to create deployment script
create_deployment_script() {
    log_info "Creating deployment script..."
    
    sudo tee /var/www/deploy-ableton-cookbook.sh << 'EOF'
#!/bin/bash

# Ableton Cookbook Laravel Deployment Script
# Zero-downtime deployment with rollback capability

set -e

DEPLOY_USER="deploy"
APP_DIR="/var/www/ableton-cookbook"
REPO_URL="https://github.com/your-username/ableton-cookbook.git"
BRANCH="main"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RELEASE_DIR="$APP_DIR/releases/$TIMESTAMP"
SHARED_DIR="$APP_DIR/shared"
CURRENT_DIR="$APP_DIR/current"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log_info() {
    echo -e "${GREEN}[DEPLOY]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Create new release directory
log_info "Creating release directory: $RELEASE_DIR"
mkdir -p "$RELEASE_DIR"

# Clone repository
log_info "Cloning repository..."
git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$RELEASE_DIR"

# Install dependencies
log_info "Installing Composer dependencies..."
cd "$RELEASE_DIR"
composer install --no-dev --optimize-autoloader --no-interaction

# Install Node.js dependencies and build assets
log_info "Building frontend assets..."
npm ci --only=production
npm run build

# Create symlinks to shared directories
log_info "Creating symlinks to shared directories..."
rm -rf "$RELEASE_DIR/storage"
ln -nfs "$SHARED_DIR/storage" "$RELEASE_DIR/storage"

rm -rf "$RELEASE_DIR/.env"
ln -nfs "$SHARED_DIR/.env" "$RELEASE_DIR/.env"

# Run Laravel commands
log_info "Running Laravel commands..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

# Set permissions
log_info "Setting permissions..."
chown -R deploy:www-data "$RELEASE_DIR"
chmod -R 755 "$RELEASE_DIR"
chmod -R 775 "$RELEASE_DIR/storage" "$RELEASE_DIR/bootstrap/cache"

# Update current symlink (zero-downtime deployment)
log_info "Updating current symlink..."
ln -nfs "$RELEASE_DIR" "$CURRENT_DIR"

# Reload PHP-FPM and Nginx
log_info "Reloading services..."
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx

# Restart queue workers
log_info "Restarting queue workers..."
sudo supervisorctl restart ableton-cookbook:*

# Cleanup old releases (keep last 5)
log_info "Cleaning up old releases..."
cd "$APP_DIR/releases"
ls -t | tail -n +6 | xargs -d '\n' rm -rf --

log_info "Deployment completed successfully!"
EOF
    
    sudo chmod +x /var/www/deploy-ableton-cookbook.sh
    sudo chown deploy:deploy /var/www/deploy-ableton-cookbook.sh
    
    log_info "Deployment script created successfully"
}

# Function to setup supervisor for queue workers
setup_supervisor() {
    log_info "Installing and configuring Supervisor for queue workers..."
    
    sudo apt install -y supervisor
    
    # Create supervisor configuration for Laravel queues
    sudo tee /etc/supervisor/conf.d/ableton-cookbook.conf << EOF
[program:ableton-cookbook-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ableton-cookbook/current/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/ableton-cookbook-worker.log
stopwaitsecs=3600

[group:ableton-cookbook]
programs=ableton-cookbook-worker
EOF
    
    sudo supervisorctl reread
    sudo supervisorctl update
    
    log_info "Supervisor configured successfully"
}

# Main execution
main() {
    log_info "Starting Ableton Cookbook production server setup..."
    
    check_root
    update_system
    install_php
    install_composer
    install_nodejs
    install_nginx
    install_mysql
    install_redis
    configure_firewall
    create_app_user
    create_app_structure
    configure_services
    install_monitoring
    setup_supervisor
    create_deployment_script
    
    log_info "🎉 Server setup completed successfully!"
    log_warn "Next steps:"
    echo "1. Configure SSH key authentication for the deploy user"
    echo "2. Set up SSL certificates using the ssl-setup.sh script"
    echo "3. Deploy your application using the deployment script"
    echo "4. Configure your GitHub Actions for automated deployments"
    echo ""
    echo "Database credentials:"
    echo "  Database: ableton_cookbook"
    echo "  Username: ableton_user"
    echo "  Password: abletonCookbook2024!"
    echo ""
    echo "Important files:"
    echo "  Nginx config: /etc/nginx/sites-available/ableton-cookbook"
    echo "  Deploy script: /var/www/deploy-ableton-cookbook.sh"
    echo "  App directory: /var/www/ableton-cookbook"
}

# Run main function
main "$@"
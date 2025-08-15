#!/bin/bash

# Ableton Cookbook Laravel Jetstream Production Server Setup
# Ubuntu 22.04 LTS Server Configuration Script
# Run as root or with sudo privileges

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DOMAIN="ableton.recipes"
DB_NAME="ableton_cookbook"
DB_USER="ableton_user"
DB_PASSWORD=""  # Will be generated
APP_USER="www-data"
SSH_PORT="22022"
PHP_VERSION="8.3"

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}" >&2
    exit 1
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

# Generate secure random password
generate_password() {
    openssl rand -base64 32 | tr -d "=+/" | cut -c1-25
}

# Update system
update_system() {
    log "Updating system packages..."
    apt update && apt upgrade -y
    apt install -y curl wget git unzip software-properties-common apt-transport-https ca-certificates gnupg lsb-release
}

# Install PHP 8.3
install_php() {
    log "Installing PHP ${PHP_VERSION}..."
    
    # Add Ondrej PPA for latest PHP
    add-apt-repository ppa:ondrej/php -y
    apt update
    
    # Install PHP and required extensions
    apt install -y \
        php${PHP_VERSION} \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-pgsql \
        php${PHP_VERSION}-sqlite3 \
        php${PHP_VERSION}-redis \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-opcache \
        php${PHP_VERSION}-fileinfo \
        php${PHP_VERSION}-tokenizer \
        php${PHP_VERSION}-dom \
        php${PHP_VERSION}-xmlwriter \
        php${PHP_VERSION}-simplexml
        
    # Configure PHP for production
    configure_php_production
}

# Configure PHP for production
configure_php_production() {
    log "Configuring PHP for production..."
    
    PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
    PHP_CLI_INI="/etc/php/${PHP_VERSION}/cli/php.ini"
    
    # Backup original configs
    cp $PHP_INI $PHP_INI.backup
    cp $PHP_CLI_INI $PHP_CLI_INI.backup
    
    # PHP-FPM configuration
    sed -i 's/memory_limit = .*/memory_limit = 512M/' $PHP_INI
    sed -i 's/upload_max_filesize = .*/upload_max_filesize = 50M/' $PHP_INI
    sed -i 's/post_max_size = .*/post_max_size = 50M/' $PHP_INI
    sed -i 's/max_execution_time = .*/max_execution_time = 300/' $PHP_INI
    sed -i 's/max_input_time = .*/max_input_time = 300/' $PHP_INI
    sed -i 's/;opcache.enable=.*/opcache.enable=1/' $PHP_INI
    sed -i 's/;opcache.memory_consumption=.*/opcache.memory_consumption=256/' $PHP_INI
    sed -i 's/;opcache.max_accelerated_files=.*/opcache.max_accelerated_files=10000/' $PHP_INI
    sed -i 's/;opcache.validate_timestamps=.*/opcache.validate_timestamps=0/' $PHP_INI
    sed -i 's/expose_php = .*/expose_php = Off/' $PHP_INI
    
    # CLI configuration
    sed -i 's/memory_limit = .*/memory_limit = 1G/' $PHP_CLI_INI
    sed -i 's/max_execution_time = .*/max_execution_time = 0/' $PHP_CLI_INI
    
    # Configure PHP-FPM pool
    cat > /etc/php/${PHP_VERSION}/fpm/pool.d/ableton.conf << EOF
[ableton]
user = www-data
group = www-data
listen = /run/php/php${PHP_VERSION}-fpm-ableton.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 1000
php_admin_value[error_log] = /var/log/php${PHP_VERSION}-fpm-ableton.log
php_admin_flag[log_errors] = on
EOF

    systemctl restart php${PHP_VERSION}-fpm
}

# Install Composer
install_composer() {
    log "Installing Composer..."
    
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    chmod +x /usr/local/bin/composer
    
    # Verify installation
    composer --version
}

# Install Nginx
install_nginx() {
    log "Installing and configuring Nginx..."
    
    apt install -y nginx
    
    # Remove default site
    rm -f /etc/nginx/sites-enabled/default
    
    # Create Nginx configuration
    create_nginx_config
    
    # Enable site
    ln -sf /etc/nginx/sites-available/ableton /etc/nginx/sites-enabled/
    
    # Test configuration
    nginx -t
    systemctl restart nginx
    systemctl enable nginx
}

# Create Nginx configuration
create_nginx_config() {
    cat > /etc/nginx/sites-available/ableton << EOF
# Rate limiting
limit_req_zone \$binary_remote_addr zone=login:10m rate=5r/m;
limit_req_zone \$binary_remote_addr zone=api:10m rate=30r/m;
limit_req_zone \$binary_remote_addr zone=upload:10m rate=2r/m;

server {
    listen 80;
    server_name ${DOMAIN} www.${DOMAIN};
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ${DOMAIN} www.${DOMAIN};
    root /var/www/ableton/public;
    index index.php;

    # SSL Configuration (will be updated by Certbot)
    ssl_certificate /etc/ssl/certs/ssl-cert-snakeoil.pem;
    ssl_certificate_key /etc/ssl/private/ssl-cert-snakeoil.key;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self';" always;
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # File upload size
    client_max_body_size 50M;
    client_body_timeout 60s;
    client_header_timeout 60s;

    # Serve static files directly
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)\$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Main application
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Rate limiting for sensitive endpoints
    location /login {
        limit_req zone=login burst=3 nodelay;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location /register {
        limit_req zone=login burst=3 nodelay;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location /api/ {
        limit_req zone=api burst=10 nodelay;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location /racks/upload {
        limit_req zone=upload burst=1 nodelay;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP processing
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm-ableton.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        
        # Security
        fastcgi_param HTTP_PROXY "";
        fastcgi_read_timeout 300s;
        fastcgi_send_timeout 300s;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ /(vendor|storage|bootstrap/cache|tests) {
        deny all;
    }

    # Logging
    access_log /var/log/nginx/ableton_access.log;
    error_log /var/log/nginx/ableton_error.log;
}
EOF
}

# Install MySQL
install_mysql() {
    log "Installing MySQL 8.0..."
    
    apt install -y mysql-server mysql-client
    
    # Generate database password
    DB_PASSWORD=$(generate_password)
    
    # Secure MySQL installation
    mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_PASSWORD}';"
    mysql -e "DELETE FROM mysql.user WHERE User='';"
    mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
    mysql -e "DROP DATABASE IF EXISTS test;"
    mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
    mysql -e "FLUSH PRIVILEGES;"
    
    # Create application database and user
    DB_USER_PASSWORD=$(generate_password)
    mysql -u root -p${DB_PASSWORD} -e "CREATE DATABASE ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -u root -p${DB_PASSWORD} -e "CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_USER_PASSWORD}';"
    mysql -u root -p${DB_PASSWORD} -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
    mysql -u root -p${DB_PASSWORD} -e "FLUSH PRIVILEGES;"
    
    # Configure MySQL for production
    configure_mysql_production
    
    # Save credentials
    cat > /root/mysql_credentials.txt << EOF
MySQL Root Password: ${DB_PASSWORD}
Database Name: ${DB_NAME}
Database User: ${DB_USER}
Database Password: ${DB_USER_PASSWORD}
EOF
    
    chmod 600 /root/mysql_credentials.txt
    
    info "MySQL credentials saved to /root/mysql_credentials.txt"
}

# Configure MySQL for production
configure_mysql_production() {
    log "Configuring MySQL for production..."
    
    cat > /etc/mysql/mysql.conf.d/laravel.cnf << EOF
[mysqld]
# Performance tuning
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
max_connections = 200
query_cache_type = 1
query_cache_size = 64M
tmp_table_size = 64M
max_heap_table_size = 64M
thread_cache_size = 50
table_open_cache = 2048

# Security
bind-address = 127.0.0.1
local-infile = 0
skip-show-database

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
log_queries_not_using_indexes = 1
EOF

    systemctl restart mysql
}

# Install Redis
install_redis() {
    log "Installing Redis..."
    
    apt install -y redis-server
    
    # Configure Redis
    sed -i 's/^supervised no/supervised systemd/' /etc/redis/redis.conf
    sed -i 's/^# maxmemory <bytes>/maxmemory 256mb/' /etc/redis/redis.conf
    sed -i 's/^# maxmemory-policy noeviction/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf
    
    systemctl restart redis-server
    systemctl enable redis-server
}

# Install Node.js and npm
install_nodejs() {
    log "Installing Node.js 20..."
    
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt install -y nodejs
    
    # Install global packages
    npm install -g pm2
    
    # Verify installation
    node --version
    npm --version
}

# Install Supervisor for queue workers
install_supervisor() {
    log "Installing Supervisor..."
    
    apt install -y supervisor
    
    # Create Laravel queue worker configuration
    cat > /etc/supervisor/conf.d/laravel-worker.conf << EOF
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ableton/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/laravel-worker.log
stopwaitsecs=3600
EOF

    systemctl enable supervisor
}

# Setup firewall
setup_firewall() {
    log "Configuring UFW firewall..."
    
    ufw --force reset
    ufw default deny incoming
    ufw default allow outgoing
    
    # Allow SSH on custom port
    ufw allow ${SSH_PORT}/tcp comment 'SSH'
    
    # Allow HTTP/HTTPS
    ufw allow 80/tcp comment 'HTTP'
    ufw allow 443/tcp comment 'HTTPS'
    
    # Enable firewall
    ufw --force enable
    
    ufw status
}

# Harden SSH
harden_ssh() {
    log "Hardening SSH configuration..."
    
    # Backup original config
    cp /etc/ssh/sshd_config /etc/ssh/sshd_config.backup
    
    # Configure SSH
    cat > /etc/ssh/sshd_config << EOF
# SSH Configuration for Production
Port ${SSH_PORT}
Protocol 2
HostKey /etc/ssh/ssh_host_rsa_key
HostKey /etc/ssh/ssh_host_ecdsa_key
HostKey /etc/ssh/ssh_host_ed25519_key

# Authentication
LoginGraceTime 60
PermitRootLogin no
StrictModes yes
MaxAuthTries 3
MaxSessions 2
PubkeyAuthentication yes
PasswordAuthentication no
PermitEmptyPasswords no
ChallengeResponseAuthentication no
UsePAM yes

# Security settings
X11Forwarding no
PrintMotd no
TCPKeepAlive yes
Compression no
ClientAliveInterval 300
ClientAliveCountMax 2
AllowTcpForwarding no
AllowStreamLocalForwarding no
GatewayPorts no
PermitTunnel no

# Logging
SyslogFacility AUTH
LogLevel VERBOSE

# Override default of no subsystems
Subsystem sftp /usr/lib/openssh/sftp-server

# Restrict users
AllowUsers deploy

AcceptEnv LANG LC_*
EOF

    # Create deploy user
    if ! id "deploy" &>/dev/null; then
        useradd -m -s /bin/bash deploy
        usermod -aG sudo deploy
        
        # Create SSH directory
        mkdir -p /home/deploy/.ssh
        chmod 700 /home/deploy/.ssh
        chown deploy:deploy /home/deploy/.ssh
        
        info "Created deploy user. Add your SSH public key to /home/deploy/.ssh/authorized_keys"
    fi
    
    # Test SSH config
    sshd -t
    
    warning "SSH will restart on port ${SSH_PORT}. Make sure you can connect before logging out!"
}

# Install fail2ban
install_fail2ban() {
    log "Installing and configuring Fail2Ban..."
    
    apt install -y fail2ban
    
    cat > /etc/fail2ban/jail.local << EOF
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 3
backend = systemd

[sshd]
enabled = true
port = ${SSH_PORT}
filter = sshd
logpath = /var/log/auth.log
maxretry = 3

[nginx-http-auth]
enabled = true
filter = nginx-http-auth
logpath = /var/log/nginx/error.log

[nginx-noscript]
enabled = true
filter = nginx-noscript
logpath = /var/log/nginx/access.log
maxretry = 6

[nginx-badbots]
enabled = true
filter = nginx-badbots
logpath = /var/log/nginx/access.log
maxretry = 2

[nginx-nohome]
enabled = true
filter = nginx-nohome
logpath = /var/log/nginx/access.log
maxretry = 2
EOF

    systemctl restart fail2ban
    systemctl enable fail2ban
}

# Setup SSL with Let's Encrypt
setup_ssl() {
    log "Installing Certbot for SSL certificates..."
    
    apt install -y certbot python3-certbot-nginx
    
    info "To setup SSL certificate, run: certbot --nginx -d ${DOMAIN} -d www.${DOMAIN}"
    info "Make sure your domain is pointing to this server's IP address first!"
}

# Create application directory structure
setup_app_structure() {
    log "Setting up application directory structure..."
    
    mkdir -p /var/www/ableton
    chown -R www-data:www-data /var/www/ableton
    chmod -R 755 /var/www/ableton
    
    # Create storage directories
    mkdir -p /var/www/ableton/storage/logs
    mkdir -p /var/www/ableton/storage/app/private/racks
    mkdir -p /var/www/ableton/storage/framework/cache
    mkdir -p /var/www/ableton/storage/framework/sessions
    mkdir -p /var/www/ableton/storage/framework/views
    
    chown -R www-data:www-data /var/www/ableton/storage
    chmod -R 775 /var/www/ableton/storage
}

# Setup log rotation
setup_logrotate() {
    log "Configuring log rotation..."
    
    cat > /etc/logrotate.d/laravel << EOF
/var/www/ableton/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    copytruncate
    su www-data www-data
}
EOF

    cat > /etc/logrotate.d/nginx-ableton << EOF
/var/log/nginx/ableton_*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    sharedscripts
    postrotate
        if [ -f /var/run/nginx.pid ]; then
            kill -USR1 \$(cat /var/run/nginx.pid)
        fi
    endscript
}
EOF
}

# Main installation function
main() {
    log "Starting Ableton Cookbook Laravel Jetstream Server Setup"
    log "Server: Ubuntu 22.04 LTS"
    log "Domain: ${DOMAIN}"
    
    # Check if running as root
    if [[ $EUID -ne 0 ]]; then
        error "This script must be run as root or with sudo"
    fi
    
    # Update system
    update_system
    
    # Install core components
    install_php
    install_composer
    install_nginx
    install_mysql
    install_redis
    install_nodejs
    install_supervisor
    
    # Security hardening
    setup_firewall
    harden_ssh
    install_fail2ban
    setup_ssl
    
    # Application setup
    setup_app_structure
    setup_logrotate
    
    log "Server setup completed successfully!"
    
    echo ""
    echo "=========================================="
    echo "NEXT STEPS:"
    echo "=========================================="
    echo "1. Add your SSH public key to /home/deploy/.ssh/authorized_keys"
    echo "2. Test SSH connection on port ${SSH_PORT}"
    echo "3. Point your domain ${DOMAIN} to this server's IP"
    echo "4. Run: certbot --nginx -d ${DOMAIN} -d www.${DOMAIN}"
    echo "5. Deploy your Laravel application to /var/www/ableton"
    echo "6. Check MySQL credentials in /root/mysql_credentials.txt"
    echo ""
    echo "Security Notes:"
    echo "- SSH is configured on port ${SSH_PORT}"
    echo "- Root login is disabled"
    echo "- Password authentication is disabled"
    echo "- Firewall is enabled with minimal ports open"
    echo "- Fail2Ban is active for intrusion detection"
    echo ""
}

# Run main function
main "$@"
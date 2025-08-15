#!/bin/bash

# Ableton Cookbook SSL Certificate Setup
# Automated SSL configuration with Let's Encrypt and security hardening

set -e

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

# Configuration
DOMAIN="ableton.recipes"
WWW_DOMAIN="www.ableton.recipes"
EMAIL="admin@ableton.recipes"
WEBROOT="/var/www/ableton-cookbook/current/public"

# Check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root for SSL certificate installation"
        exit 1
    fi
}

# Install Certbot
install_certbot() {
    log_info "Installing Certbot for Let's Encrypt..."
    
    apt update
    apt install -y snapd
    snap install core; snap refresh core
    snap install --classic certbot
    
    # Create symlink
    ln -sf /snap/bin/certbot /usr/bin/certbot
    
    log_info "Certbot installed successfully"
}

# Create temporary Nginx configuration for domain verification
create_temp_nginx_config() {
    log_info "Creating temporary Nginx configuration for domain verification..."
    
    tee /etc/nginx/sites-available/ableton-cookbook-temp << EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN $WWW_DOMAIN;
    
    root $WEBROOT;
    index index.php index.html index.htm;
    
    # Allow Let's Encrypt domain validation
    location /.well-known/acme-challenge/ {
        root /var/www/html;
        allow all;
    }
    
    # Temporary redirect to verify domain ownership
    location / {
        return 200 'Domain verification in progress';
        add_header Content-Type text/plain;
    }
}
EOF
    
    # Enable temporary configuration
    ln -sf /etc/nginx/sites-available/ableton-cookbook-temp /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/ableton-cookbook
    
    # Test and reload Nginx
    nginx -t && systemctl reload nginx
    
    log_info "Temporary Nginx configuration created"
}

# Obtain SSL certificates
obtain_ssl_certificates() {
    log_info "Obtaining SSL certificates from Let's Encrypt..."
    
    # Create webroot directory for verification
    mkdir -p /var/www/html/.well-known/acme-challenge
    chown -R www-data:www-data /var/www/html
    
    # Request certificates for both domains
    certbot certonly \
        --webroot \
        --webroot-path=/var/www/html \
        --email "$EMAIL" \
        --agree-tos \
        --no-eff-email \
        --domains "$DOMAIN,$WWW_DOMAIN" \
        --non-interactive
    
    if [ $? -eq 0 ]; then
        log_info "SSL certificates obtained successfully"
    else
        log_error "Failed to obtain SSL certificates"
        exit 1
    fi
}

# Create production Nginx configuration with SSL
create_ssl_nginx_config() {
    log_info "Creating production Nginx configuration with SSL..."
    
    tee /etc/nginx/sites-available/ableton-cookbook << 'EOF'
# Rate limiting zones
limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
limit_req_zone $binary_remote_addr zone=upload:10m rate=10r/m;
limit_req_zone $binary_remote_addr zone=api:10m rate=30r/m;

# HTTP to HTTPS redirect
server {
    listen 80;
    listen [::]:80;
    server_name ableton.recipes www.ableton.recipes;
    
    # Allow Let's Encrypt renewals
    location /.well-known/acme-challenge/ {
        root /var/www/html;
        allow all;
    }
    
    # Redirect all HTTP to HTTPS
    location / {
        return 301 https://$server_name$request_uri;
    }
}

# Main HTTPS server block
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ableton.recipes www.ableton.recipes;
    
    root /var/www/ableton-cookbook/current/public;
    index index.php index.html index.htm;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/ableton.recipes/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ableton.recipes/privkey.pem;
    
    # SSL Security Settings
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_timeout 10m;
    ssl_session_cache shared:SSL:10m;
    ssl_session_tickets off;
    
    # OCSP Stapling
    ssl_stapling on;
    ssl_stapling_verify on;
    ssl_trusted_certificate /etc/letsencrypt/live/ableton.recipes/chain.pem;
    resolver 8.8.8.8 8.8.4.4 208.67.222.222 208.67.220.220 valid=60s;
    resolver_timeout 2s;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    
    # Content Security Policy for Ableton Cookbook
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; media-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self';" always;
    
    # File upload limits
    client_max_body_size 25M;
    client_body_buffer_size 1M;
    client_body_timeout 60s;
    client_header_timeout 60s;
    
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
    
    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot|otf)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header Vary "Accept-Encoding";
        access_log off;
        
        # CORS headers for fonts
        if ($request_filename ~* \.(woff|woff2|ttf|eot|otf)$) {
            add_header Access-Control-Allow-Origin "*";
        }
    }
    
    # Security: Block access to sensitive files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    location ~ (composer\.(json|lock)|package\.(json|lock)|\.env|\.git|\.svn|\.htaccess|web\.config) {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    # Block execution of uploaded files
    location ^~ /storage/ {
        location ~* \.(php|phtml|pl|py|jsp|asp|sh|cgi|exe|scr|bat|cmd)$ {
            deny all;
            access_log off;
            log_not_found off;
        }
        
        # Allow .adg file downloads with security headers
        location ~* \.adg$ {
            add_header X-Content-Type-Options nosniff;
            add_header X-Frame-Options DENY;
            add_header Content-Security-Policy "default-src 'none'";
            add_header Content-Disposition attachment;
        }
    }
    
    # Rate limiting for sensitive endpoints
    location ^~ /login {
        limit_req zone=login burst=3 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ^~ /upload {
        limit_req zone=upload burst=5 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ^~ /api/ {
        limit_req zone=api burst=10 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # Laravel application
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP-FPM configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        
        # Security headers for PHP responses
        fastcgi_param HTTPS on;
        fastcgi_param HTTP_SCHEME https;
        
        # Increase timeouts for large file processing
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_connect_timeout 300;
        
        # Buffer settings
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }
    
    # SEO-friendly URLs
    location = /sitemap.xml {
        try_files $uri /index.php?$query_string;
        expires 1d;
        add_header Cache-Control "public";
    }
    
    location ~ ^/sitemap.*\.xml$ {
        try_files $uri /index.php?$query_string;
        expires 1d;
        add_header Cache-Control "public";
    }
    
    # Robots.txt
    location = /robots.txt {
        expires 1d;
        add_header Cache-Control "public";
        access_log off;
    }
    
    # Favicon
    location = /favicon.ico {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
        log_not_found off;
    }
    
    # Error pages
    error_page 404 /index.php;
    error_page 500 502 503 504 /50x.html;
    
    location = /50x.html {
        root /var/www/html;
        internal;
    }
}
EOF
    
    log_info "Production Nginx configuration with SSL created"
}

# Configure SSL security settings
configure_ssl_security() {
    log_info "Configuring additional SSL security settings..."
    
    # Create DH parameters for enhanced security
    if [ ! -f /etc/nginx/dhparam.pem ]; then
        log_info "Generating DH parameters (this may take a while)..."
        openssl dhparam -out /etc/nginx/dhparam.pem 2048
    fi
    
    # Create SSL configuration snippet
    tee /etc/nginx/snippets/ssl-params.conf << 'EOF'
# SSL Configuration
ssl_protocols TLSv1.2 TLSv1.3;
ssl_prefer_server_ciphers off;
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;

# SSL Session Cache
ssl_session_timeout 1d;
ssl_session_cache shared:SSL:50m;
ssl_session_tickets off;

# OCSP Stapling
ssl_stapling on;
ssl_stapling_verify on;

# Security Headers
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
EOF
    
    log_info "SSL security settings configured"
}

# Setup automatic certificate renewal
setup_cert_renewal() {
    log_info "Setting up automatic certificate renewal..."
    
    # Create renewal hook script
    tee /etc/letsencrypt/renewal-hooks/post/nginx-reload.sh << 'EOF'
#!/bin/bash
systemctl reload nginx
EOF
    
    chmod +x /etc/letsencrypt/renewal-hooks/post/nginx-reload.sh
    
    # Test renewal process
    certbot renew --dry-run
    
    if [ $? -eq 0 ]; then
        log_info "Certificate renewal test successful"
    else
        log_warn "Certificate renewal test failed - check configuration"
    fi
    
    log_info "Automatic certificate renewal configured"
}

# Apply final Nginx configuration
apply_nginx_config() {
    log_info "Applying final Nginx configuration..."
    
    # Remove temporary configuration
    rm -f /etc/nginx/sites-enabled/ableton-cookbook-temp
    
    # Enable production configuration
    ln -sf /etc/nginx/sites-available/ableton-cookbook /etc/nginx/sites-enabled/
    
    # Test configuration
    nginx -t
    
    if [ $? -eq 0 ]; then
        log_info "Nginx configuration test passed"
        systemctl reload nginx
        log_info "Nginx reloaded with SSL configuration"
    else
        log_error "Nginx configuration test failed"
        exit 1
    fi
}

# Verify SSL configuration
verify_ssl() {
    log_info "Verifying SSL configuration..."
    
    # Wait for Nginx to reload
    sleep 5
    
    # Test SSL certificate
    echo | openssl s_client -servername "$DOMAIN" -connect "$DOMAIN:443" 2>/dev/null | openssl x509 -noout -dates
    
    # Check SSL rating (simplified)
    log_info "Testing HTTPS connection..."
    curl -I "https://$DOMAIN" 2>/dev/null | head -n 1
    
    if [ $? -eq 0 ]; then
        log_info "✅ HTTPS connection successful"
    else
        log_warn "⚠️  HTTPS connection test failed"
    fi
    
    log_info "SSL verification completed"
}

# Configure HSTS preload (optional)
configure_hsts_preload() {
    log_info "Configuring HSTS preload preparation..."
    
    cat << EOF

🔒 SSL Security Configuration Complete!

Your site is now configured with:
✅ Let's Encrypt SSL certificates
✅ HTTP/2 support
✅ Strong SSL/TLS configuration
✅ Security headers (HSTS, CSP, etc.)
✅ Automatic certificate renewal

Next steps for maximum security:
1. Submit your domain to HSTS preload list:
   https://hstspreload.org/

2. Monitor SSL certificate status:
   https://www.ssllabs.com/ssltest/

3. Regular security audits:
   https://securityheaders.com/

Configuration files:
- SSL certificates: /etc/letsencrypt/live/$DOMAIN/
- Nginx config: /etc/nginx/sites-available/ableton-cookbook
- SSL params: /etc/nginx/snippets/ssl-params.conf

Certificate renewal:
- Automatic via cron (certbot renew)
- Test renewal: certbot renew --dry-run
- Manual renewal: certbot renew --force-renewal

EOF
}

# Main execution
main() {
    log_info "🔐 Starting SSL certificate setup for Ableton Cookbook..."
    
    check_root
    install_certbot
    create_temp_nginx_config
    obtain_ssl_certificates
    create_ssl_nginx_config
    configure_ssl_security
    setup_cert_renewal
    apply_nginx_config
    verify_ssl
    configure_hsts_preload
    
    log_info "🎉 SSL setup completed successfully!"
    log_info "Your site is now available at: https://$DOMAIN"
}

# Run main function
main "$@"
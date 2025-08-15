#!/bin/bash

# Ableton Cookbook Performance Optimization
# Production performance tuning for Laravel Jetstream application

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
APP_DIR="/var/www/ableton-cookbook"
SHARED_DIR="$APP_DIR/shared"
CURRENT_DIR="$APP_DIR/current"

# Optimize PHP-FPM configuration
optimize_php_fpm() {
    log_info "Optimizing PHP-FPM configuration for production..."
    
    # Backup original configuration
    sudo cp /etc/php/8.2/fpm/pool.d/www.conf /etc/php/8.2/fpm/pool.d/www.conf.backup
    
    # Create optimized PHP-FPM pool configuration
    sudo tee /etc/php/8.2/fpm/pool.d/ableton-cookbook.conf << 'EOF'
[ableton-cookbook]
user = deploy
group = www-data
listen = /var/run/php/php8.2-fpm-ableton.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Process management
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests = 1000

; Performance tuning
pm.process_idle_timeout = 10s
request_terminate_timeout = 300
request_slowlog_timeout = 30s
slowlog = /var/log/php8.2-fpm-slow.log

; Security
security.limit_extensions = .php
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source

; Memory and execution limits
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 300
php_admin_value[max_input_time] = 300
php_admin_value[upload_max_filesize] = 25M
php_admin_value[post_max_size] = 30M

; Session configuration
php_value[session.save_handler] = redis
php_value[session.save_path] = "tcp://127.0.0.1:6379"
php_value[session.gc_maxlifetime] = 7200

; OPcache configuration
php_admin_value[opcache.enable] = 1
php_admin_value[opcache.memory_consumption] = 256
php_admin_value[opcache.interned_strings_buffer] = 16
php_admin_value[opcache.max_accelerated_files] = 10000
php_admin_value[opcache.revalidate_freq] = 0
php_admin_value[opcache.validate_timestamps] = 0
php_admin_value[opcache.fast_shutdown] = 1
php_admin_value[opcache.enable_file_override] = 1

; Realpath cache
php_admin_value[realpath_cache_size] = 4M
php_admin_value[realpath_cache_ttl] = 7200

; File uploads for .adg files
php_admin_value[file_uploads] = On
php_admin_value[max_file_uploads] = 5

; Error handling (production)
php_admin_value[display_errors] = Off
php_admin_value[log_errors] = On
php_admin_value[error_log] = /var/log/php8.2-fpm-errors.log
EOF
    
    # Disable default www pool
    sudo mv /etc/php/8.2/fpm/pool.d/www.conf /etc/php/8.2/fpm/pool.d/www.conf.disabled
    
    log_info "PHP-FPM configuration optimized"
}

# Configure MySQL for optimal performance
optimize_mysql() {
    log_info "Optimizing MySQL configuration..."
    
    # Backup original configuration
    sudo cp /etc/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/mysql.conf.d/mysqld.cnf.backup
    
    # Create optimized MySQL configuration
    sudo tee -a /etc/mysql/mysql.conf.d/mysqld.cnf << 'EOF'

# Ableton Cookbook Performance Optimizations
[mysqld]

# Connection settings
max_connections = 200
max_user_connections = 180
thread_cache_size = 50
table_open_cache = 4000
table_definition_cache = 2000

# InnoDB settings for SSD
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_log_buffer_size = 64M
innodb_flush_log_at_trx_commit = 2
innodb_file_per_table = 1
innodb_flush_method = O_DIRECT
innodb_io_capacity = 2000
innodb_io_capacity_max = 4000
innodb_read_io_threads = 4
innodb_write_io_threads = 4

# Query cache (for read-heavy workloads)
query_cache_type = 1
query_cache_size = 128M
query_cache_limit = 4M

# Temporary tables
tmp_table_size = 128M
max_heap_table_size = 128M

# MyISAM settings
key_buffer_size = 64M
myisam_sort_buffer_size = 128M

# Binary logging
binlog_cache_size = 32K
max_binlog_cache_size = 512K
max_binlog_size = 1G
expire_logs_days = 7

# Slow query log
slow_query_log = 1
slow_query_log_file = /var/log/mysql/mysql-slow.log
long_query_time = 2

# Performance schema
performance_schema = ON
performance_schema_max_table_instances = 12500
performance_schema_max_table_handles = 4000

# Character set
character_set_server = utf8mb4
collation_server = utf8mb4_unicode_ci

# Networking
max_allowed_packet = 64M
net_buffer_length = 32K
EOF
    
    log_info "MySQL configuration optimized"
}

# Configure Redis for optimal performance
optimize_redis() {
    log_info "Optimizing Redis configuration..."
    
    # Backup original configuration
    sudo cp /etc/redis/redis.conf /etc/redis/redis.conf.backup
    
    # Apply Redis optimizations
    sudo tee -a /etc/redis/redis.conf << 'EOF'

# Ableton Cookbook Redis Optimizations

# Memory management
maxmemory 512mb
maxmemory-policy allkeys-lru
maxmemory-samples 5

# Persistence (optimized for cache usage)
save ""
stop-writes-on-bgsave-error no
rdbcompression yes
rdbchecksum yes

# Performance
tcp-keepalive 300
timeout 0
tcp-backlog 511
databases 16

# Lazy freeing
lazyfree-lazy-eviction yes
lazyfree-lazy-expire yes
lazyfree-lazy-server-del yes

# Client output buffer limits
client-output-buffer-limit normal 0 0 0
client-output-buffer-limit replica 256mb 64mb 60
client-output-buffer-limit pubsub 32mb 8mb 60

# Hash table optimization
hash-max-ziplist-entries 512
hash-max-ziplist-value 64
list-max-ziplist-size -2
list-compress-depth 0
set-max-intset-entries 512
zset-max-ziplist-entries 128
zset-max-ziplist-value 64

# Security
protected-mode yes
port 6379
bind 127.0.0.1 ::1
EOF
    
    log_info "Redis configuration optimized"
}

# Optimize Laravel application
optimize_laravel() {
    log_info "Optimizing Laravel application..."
    
    cd "$CURRENT_DIR"
    
    # Clear all caches first
    sudo -u deploy php artisan cache:clear
    sudo -u deploy php artisan config:clear
    sudo -u deploy php artisan route:clear
    sudo -u deploy php artisan view:clear
    
    # Generate optimized autoloader
    sudo -u deploy composer install --no-dev --optimize-autoloader --no-interaction
    sudo -u deploy composer dump-autoload --optimize --no-dev
    
    # Cache configuration, routes, and views
    sudo -u deploy php artisan config:cache
    sudo -u deploy php artisan route:cache
    sudo -u deploy php artisan view:cache
    
    # Cache events (if using event discovery)
    sudo -u deploy php artisan event:cache || true
    
    log_info "Laravel application optimized"
}

# Create performance monitoring script
create_monitoring_script() {
    log_info "Creating performance monitoring script..."
    
    sudo -u deploy mkdir -p "$SHARED_DIR/scripts/monitoring"
    
    cat << 'EOF' > "$SHARED_DIR/scripts/monitoring/performance-monitor.sh"
#!/bin/bash

# Ableton Cookbook Performance Monitoring
# Monitors system and application performance metrics

LOG_FILE="/var/log/ableton-cookbook/performance.log"
ALERT_THRESHOLD_CPU=80
ALERT_THRESHOLD_MEMORY=85
ALERT_THRESHOLD_DISK=90

log_metric() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# System metrics
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | awk -F'%' '{print $1}')
MEMORY_USAGE=$(free | grep Mem | awk '{printf("%.1f"), $3/$2 * 100.0}')
DISK_USAGE=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')

# PHP-FPM metrics
PHP_FPM_PROCESSES=$(pgrep -c php-fpm)
PHP_FPM_MEMORY=$(ps aux | grep php-fpm | awk '{sum+=$6} END {printf("%.1f"), sum/1024}')

# MySQL metrics
MYSQL_CONNECTIONS=$(mysql -e "SHOW STATUS LIKE 'Threads_connected';" | tail -1 | awk '{print $2}')
MYSQL_QUERIES=$(mysql -e "SHOW STATUS LIKE 'Queries';" | tail -1 | awk '{print $2}')

# Redis metrics
REDIS_MEMORY=$(redis-cli info memory | grep used_memory_human | cut -d: -f2 | tr -d '\r')
REDIS_KEYS=$(redis-cli dbsize)

# Nginx metrics
NGINX_ACTIVE=$(curl -s http://localhost/nginx_status | grep "Active connections" | awk '{print $3}' || echo "N/A")

# Log metrics
log_metric "CPU: ${CPU_USAGE}% | Memory: ${MEMORY_USAGE}% | Disk: ${DISK_USAGE}%"
log_metric "PHP-FPM: ${PHP_FPM_PROCESSES} processes, ${PHP_FPM_MEMORY}MB memory"
log_metric "MySQL: ${MYSQL_CONNECTIONS} connections, ${MYSQL_QUERIES} queries"
log_metric "Redis: ${REDIS_MEMORY} memory, ${REDIS_KEYS} keys"
log_metric "Nginx: ${NGINX_ACTIVE} active connections"

# Alerts
if (( $(echo "$CPU_USAGE > $ALERT_THRESHOLD_CPU" | bc -l) )); then
    log_metric "ALERT: High CPU usage: ${CPU_USAGE}%"
fi

if (( $(echo "$MEMORY_USAGE > $ALERT_THRESHOLD_MEMORY" | bc -l) )); then
    log_metric "ALERT: High memory usage: ${MEMORY_USAGE}%"
fi

if [ "$DISK_USAGE" -gt "$ALERT_THRESHOLD_DISK" ]; then
    log_metric "ALERT: High disk usage: ${DISK_USAGE}%"
fi

# Application-specific metrics
APP_RESPONSE_TIME=$(curl -o /dev/null -s -w '%{time_total}' https://ableton.recipes || echo "0")
log_metric "App response time: ${APP_RESPONSE_TIME}s"

if (( $(echo "$APP_RESPONSE_TIME > 3.0" | bc -l) )); then
    log_metric "ALERT: Slow application response: ${APP_RESPONSE_TIME}s"
fi
EOF
    
    chmod +x "$SHARED_DIR/scripts/monitoring/performance-monitor.sh"
    chown deploy:deploy "$SHARED_DIR/scripts/monitoring/performance-monitor.sh"
    
    # Add to crontab (run every 5 minutes)
    (crontab -u deploy -l 2>/dev/null || true; echo "*/5 * * * * $SHARED_DIR/scripts/monitoring/performance-monitor.sh") | crontab -u deploy -
    
    log_info "Performance monitoring script created"
}

# Create performance optimization script for Laravel
create_laravel_optimization_script() {
    log_info "Creating Laravel performance optimization script..."
    
    cat << 'EOF' > "$SHARED_DIR/scripts/optimize-laravel.sh"
#!/bin/bash

# Laravel Performance Optimization Script
# Run this after each deployment

cd /var/www/ableton-cookbook/current

echo "🚀 Optimizing Laravel application..."

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimize Composer autoloader
composer dump-autoload --optimize --no-dev

# Cache everything
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Cache events
php artisan event:cache || true

# Optimize for production
php artisan optimize

# Restart queue workers
sudo supervisorctl restart ableton-cookbook:*

# Restart PHP-FPM for good measure
sudo systemctl reload php8.2-fpm

echo "✅ Laravel optimization completed!"
EOF
    
    chmod +x "$SHARED_DIR/scripts/optimize-laravel.sh"
    chown deploy:deploy "$SHARED_DIR/scripts/optimize-laravel.sh"
    
    log_info "Laravel optimization script created"
}

# Configure system-level optimizations
optimize_system() {
    log_info "Applying system-level optimizations..."
    
    # Kernel parameters for web server performance
    sudo tee -a /etc/sysctl.conf << 'EOF'

# Ableton Cookbook System Optimizations

# Network performance
net.core.rmem_max = 16777216
net.core.wmem_max = 16777216
net.ipv4.tcp_rmem = 4096 87380 16777216
net.ipv4.tcp_wmem = 4096 65536 16777216
net.ipv4.tcp_congestion_control = bbr
net.core.netdev_max_backlog = 5000
net.ipv4.tcp_max_syn_backlog = 4096

# File descriptor limits
fs.file-max = 100000

# Memory management
vm.swappiness = 10
vm.dirty_ratio = 15
vm.dirty_background_ratio = 5

# Security
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.default.rp_filter = 1
net.ipv4.icmp_echo_ignore_broadcasts = 1
net.ipv4.icmp_ignore_bogus_error_responses = 1
net.ipv4.tcp_syncookies = 1
EOF
    
    # Apply sysctl changes
    sudo sysctl -p
    
    # Increase file descriptor limits
    sudo tee -a /etc/security/limits.conf << 'EOF'
# File descriptor limits for web server
deploy soft nofile 65536
deploy hard nofile 65536
www-data soft nofile 65536
www-data hard nofile 65536
EOF
    
    log_info "System optimizations applied"
}

# Configure log rotation
setup_log_rotation() {
    log_info "Setting up log rotation..."
    
    # Laravel log rotation
    sudo tee /etc/logrotate.d/ableton-cookbook << 'EOF'
/var/www/ableton-cookbook/current/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0644 deploy www-data
    postrotate
        systemctl reload php8.2-fpm
    endscript
}
EOF
    
    # Application-specific log rotation
    sudo tee /etc/logrotate.d/ableton-cookbook-custom << 'EOF'
/var/log/ableton-cookbook/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 0644 deploy deploy
}
EOF
    
    # PHP-FPM log rotation
    sudo tee /etc/logrotate.d/php8.2-fpm-custom << 'EOF'
/var/log/php8.2-fpm*.log {
    daily
    missingok
    rotate 7
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
    postrotate
        systemctl reload php8.2-fpm
    endscript
}
EOF
    
    log_info "Log rotation configured"
}

# Create cache warming script
create_cache_warming_script() {
    log_info "Creating cache warming script..."
    
    cat << 'EOF' > "$SHARED_DIR/scripts/warm-cache.sh"
#!/bin/bash

# Ableton Cookbook Cache Warming
# Pre-loads frequently accessed pages to improve initial response times

DOMAIN="https://ableton.recipes"
LOG_FILE="/var/log/ableton-cookbook/cache-warming.log"

log_warming() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

log_warming "Starting cache warming process..."

# Warm up main pages
PAGES=(
    "/"
    "/upload"
    "/login"
    "/register"
    "/sitemap.xml"
)

for page in "${PAGES[@]}"; do
    log_warming "Warming: $page"
    curl -s -o /dev/null "$DOMAIN$page" || log_warming "Failed to warm: $page"
done

# Warm up some rack pages (first 10)
RACK_IDS=$(mysql -h localhost -u ableton_user -p'abletonCookbook2024!' -D ableton_cookbook -e "SELECT id FROM racks WHERE is_public = 1 AND status = 'approved' ORDER BY views_count DESC LIMIT 10;" -N 2>/dev/null || echo "")

if [ -n "$RACK_IDS" ]; then
    for rack_id in $RACK_IDS; do
        log_warming "Warming rack: /racks/$rack_id"
        curl -s -o /dev/null "$DOMAIN/racks/$rack_id" || log_warming "Failed to warm rack: $rack_id"
    done
fi

log_warming "Cache warming completed"
EOF
    
    chmod +x "$SHARED_DIR/scripts/warm-cache.sh"
    chown deploy:deploy "$SHARED_DIR/scripts/warm-cache.sh"
    
    # Add to crontab (run every hour)
    (crontab -u deploy -l 2>/dev/null || true; echo "0 * * * * $SHARED_DIR/scripts/warm-cache.sh") | crontab -u deploy -
    
    log_info "Cache warming script created"
}

# Restart all services
restart_services() {
    log_info "Restarting optimized services..."
    
    # Restart services to apply optimizations
    sudo systemctl restart mysql
    sudo systemctl restart redis-server
    sudo systemctl restart php8.2-fpm
    sudo systemctl reload nginx
    
    # Restart supervisor for queue workers
    sudo systemctl restart supervisor
    
    log_info "All services restarted with optimizations"
}

# Generate performance report
generate_performance_report() {
    log_info "Generating performance report..."
    
    cat << EOF

🚀 Ableton Cookbook Performance Optimization Complete!

Applied optimizations:
✅ PHP-FPM optimized for high traffic
✅ MySQL configured for SSD and read-heavy workloads
✅ Redis optimized for caching and sessions
✅ Laravel application caches optimized
✅ System kernel parameters tuned
✅ Log rotation configured
✅ Performance monitoring enabled
✅ Cache warming scheduled

Key performance improvements:
- PHP-FPM: Dynamic process management with up to 20 children
- MySQL: 1GB InnoDB buffer pool, optimized for SSD
- Redis: 512MB memory limit with LRU eviction
- Laravel: All caches enabled (config, routes, views)
- System: BBR congestion control, optimized file descriptors

Monitoring:
- Performance metrics logged every 5 minutes
- Automatic alerts for high resource usage
- Cache warming every hour for popular pages

Scripts created:
- $SHARED_DIR/scripts/optimize-laravel.sh
- $SHARED_DIR/scripts/monitoring/performance-monitor.sh
- $SHARED_DIR/scripts/warm-cache.sh

Performance logs:
- /var/log/ableton-cookbook/performance.log
- /var/log/php8.2-fpm-slow.log
- /var/log/mysql/mysql-slow.log

Next steps:
1. Monitor performance metrics in logs
2. Run load testing to validate optimizations
3. Adjust settings based on actual traffic patterns
4. Consider CDN for static assets if needed

EOF
}

# Main execution
main() {
    log_info "🚀 Starting performance optimization for Ableton Cookbook..."
    
    optimize_php_fpm
    optimize_mysql
    optimize_redis
    optimize_laravel
    optimize_system
    setup_log_rotation
    create_monitoring_script
    create_laravel_optimization_script
    create_cache_warming_script
    restart_services
    generate_performance_report
    
    log_info "🎉 Performance optimization completed successfully!"
}

# Run main function
main "$@"
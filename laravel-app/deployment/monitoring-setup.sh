#!/bin/bash

# Ableton Cookbook Monitoring and Maintenance Setup
# Comprehensive monitoring, alerting, and maintenance automation

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
MONITORING_DIR="$SHARED_DIR/monitoring"
LOG_DIR="/var/log/ableton-cookbook"
ADMIN_EMAIL="admin@ableton.recipes"

# Create monitoring directory structure
create_monitoring_structure() {
    log_info "Creating monitoring directory structure..."
    
    sudo -u deploy mkdir -p "$MONITORING_DIR"/{scripts,data,alerts,reports}
    sudo -u deploy mkdir -p "$LOG_DIR"
    
    # Set proper permissions
    sudo chown -R deploy:deploy "$MONITORING_DIR"
    sudo chmod -R 755 "$MONITORING_DIR"
    
    log_info "Monitoring directory structure created"
}

# Install monitoring tools
install_monitoring_tools() {
    log_info "Installing monitoring tools..."
    
    # Update package list
    sudo apt update
    
    # Install essential monitoring tools
    sudo apt install -y \
        htop \
        iotop \
        nethogs \
        nload \
        ncdu \
        tree \
        jq \
        bc \
        mailutils \
        postfix \
        logwatch \
        monit
    
    # Configure postfix for local mail delivery
    sudo debconf-set-selections <<< "postfix postfix/mailname string ableton.recipes"
    sudo debconf-set-selections <<< "postfix postfix/main_mailer_type string 'Local only'"
    sudo dpkg-reconfigure -f noninteractive postfix
    
    log_info "Monitoring tools installed"
}

# Create comprehensive health check script
create_health_check_script() {
    log_info "Creating comprehensive health check script..."
    
    cat << 'EOF' > "$MONITORING_DIR/scripts/health-check.sh"
#!/bin/bash

# Ableton Cookbook Health Check
# Comprehensive application and system health monitoring

HEALTH_LOG="/var/log/ableton-cookbook/health-check.log"
ALERT_LOG="/var/log/ableton-cookbook/alerts.log"
DOMAIN="https://ableton.recipes"
DB_USER="ableton_user"
DB_PASS="abletonCookbook2024!"
DB_NAME="ableton_cookbook"

# Health check thresholds
CPU_THRESHOLD=80
MEMORY_THRESHOLD=85
DISK_THRESHOLD=90
RESPONSE_TIME_THRESHOLD=3.0
ERROR_RATE_THRESHOLD=5

log_health() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$HEALTH_LOG"
}

log_alert() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ALERT: $1" >> "$ALERT_LOG"
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ALERT: $1" >> "$HEALTH_LOG"
}

# System health checks
check_system_resources() {
    log_health "=== System Resource Check ==="
    
    # CPU usage
    CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | awk -F'%' '{print $1}')
    log_health "CPU Usage: ${CPU_USAGE}%"
    
    if (( $(echo "$CPU_USAGE > $CPU_THRESHOLD" | bc -l) )); then
        log_alert "High CPU usage: ${CPU_USAGE}%"
    fi
    
    # Memory usage
    MEMORY_USAGE=$(free | grep Mem | awk '{printf("%.1f"), $3/$2 * 100.0}')
    log_health "Memory Usage: ${MEMORY_USAGE}%"
    
    if (( $(echo "$MEMORY_USAGE > $MEMORY_THRESHOLD" | bc -l) )); then
        log_alert "High memory usage: ${MEMORY_USAGE}%"
    fi
    
    # Disk usage
    DISK_USAGE=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')
    log_health "Disk Usage: ${DISK_USAGE}%"
    
    if [ "$DISK_USAGE" -gt "$DISK_THRESHOLD" ]; then
        log_alert "High disk usage: ${DISK_USAGE}%"
    fi
    
    # Load average
    LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}')
    log_health "Load Average:$LOAD_AVG"
}

# Service health checks
check_services() {
    log_health "=== Service Health Check ==="
    
    SERVICES=("nginx" "php8.2-fpm" "mysql" "redis-server" "supervisor")
    
    for service in "${SERVICES[@]}"; do
        if systemctl is-active --quiet "$service"; then
            log_health "$service: RUNNING"
        else
            log_alert "$service is not running"
        fi
    done
}

# Application health checks
check_application() {
    log_health "=== Application Health Check ==="
    
    # HTTP response check
    RESPONSE_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$DOMAIN" || echo "000")
    RESPONSE_TIME=$(curl -o /dev/null -s -w '%{time_total}' "$DOMAIN" || echo "999")
    
    log_health "HTTP Response Code: $RESPONSE_CODE"
    log_health "Response Time: ${RESPONSE_TIME}s"
    
    if [ "$RESPONSE_CODE" != "200" ]; then
        log_alert "Application not responding correctly (HTTP $RESPONSE_CODE)"
    fi
    
    if (( $(echo "$RESPONSE_TIME > $RESPONSE_TIME_THRESHOLD" | bc -l) )); then
        log_alert "Slow application response: ${RESPONSE_TIME}s"
    fi
    
    # Database connectivity
    if mysql -h localhost -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1" "$DB_NAME" >/dev/null 2>&1; then
        log_health "Database: CONNECTED"
    else
        log_alert "Database connection failed"
    fi
    
    # Redis connectivity
    if redis-cli ping >/dev/null 2>&1; then
        log_health "Redis: CONNECTED"
    else
        log_alert "Redis connection failed"
    fi
    
    # Queue workers
    QUEUE_WORKERS=$(ps aux | grep -c "queue:work" || echo "0")
    log_health "Queue Workers: $QUEUE_WORKERS running"
    
    if [ "$QUEUE_WORKERS" -eq 0 ]; then
        log_alert "No queue workers running"
    fi
}

# Check application errors
check_application_errors() {
    log_health "=== Application Error Check ==="
    
    # Check Laravel logs for recent errors
    ERROR_COUNT=$(grep -c "ERROR" /var/www/ableton-cookbook/current/storage/logs/laravel-$(date +%Y-%m-%d).log 2>/dev/null || echo "0")
    log_health "Recent Laravel errors: $ERROR_COUNT"
    
    if [ "$ERROR_COUNT" -gt "$ERROR_RATE_THRESHOLD" ]; then
        log_alert "High error rate: $ERROR_COUNT errors today"
    fi
    
    # Check PHP-FPM errors
    PHP_ERRORS=$(grep -c "WARNING\|ERROR" /var/log/php8.2-fpm.log 2>/dev/null || echo "0")
    log_health "PHP-FPM warnings/errors: $PHP_ERRORS"
    
    # Check Nginx errors
    NGINX_ERRORS=$(grep -c "error" /var/log/nginx/error.log 2>/dev/null || echo "0")
    log_health "Nginx errors: $NGINX_ERRORS"
}

# Check file upload functionality
check_file_uploads() {
    log_health "=== File Upload Health Check ==="
    
    UPLOAD_DIR="/var/www/ableton-cookbook/shared/storage/app/private/racks"
    TEMP_DIR="/var/www/ableton-cookbook/shared/storage/app/temp"
    QUARANTINE_DIR="/var/www/ableton-cookbook/shared/storage/app/quarantine"
    
    # Check directory permissions
    if [ -w "$UPLOAD_DIR" ]; then
        log_health "Upload directory: WRITABLE"
    else
        log_alert "Upload directory not writable: $UPLOAD_DIR"
    fi
    
    # Check available disk space for uploads
    UPLOAD_SPACE=$(df "$UPLOAD_DIR" | awk 'NR==2 {print $4}')
    log_health "Upload space available: ${UPLOAD_SPACE}KB"
    
    if [ "$UPLOAD_SPACE" -lt 1048576 ]; then # Less than 1GB
        log_alert "Low disk space for uploads: ${UPLOAD_SPACE}KB"
    fi
    
    # Check ClamAV daemon
    if pgrep clamd >/dev/null; then
        log_health "ClamAV daemon: RUNNING"
    else
        log_alert "ClamAV daemon not running"
    fi
    
    # Check quarantine directory size
    QUARANTINE_SIZE=$(du -s "$QUARANTINE_DIR" 2>/dev/null | awk '{print $1}' || echo "0")
    log_health "Quarantine directory size: ${QUARANTINE_SIZE}KB"
    
    if [ "$QUARANTINE_SIZE" -gt 1048576 ]; then # More than 1GB
        log_alert "Large quarantine directory: ${QUARANTINE_SIZE}KB"
    fi
}

# Check SSL certificate expiration
check_ssl_certificate() {
    log_health "=== SSL Certificate Check ==="
    
    CERT_EXPIRY=$(echo | openssl s_client -servername ableton.recipes -connect ableton.recipes:443 2>/dev/null | openssl x509 -noout -enddate | cut -d= -f2)
    CERT_EXPIRY_EPOCH=$(date -d "$CERT_EXPIRY" +%s)
    CURRENT_EPOCH=$(date +%s)
    DAYS_UNTIL_EXPIRY=$(( (CERT_EXPIRY_EPOCH - CURRENT_EPOCH) / 86400 ))
    
    log_health "SSL certificate expires in: $DAYS_UNTIL_EXPIRY days"
    
    if [ "$DAYS_UNTIL_EXPIRY" -lt 30 ]; then
        log_alert "SSL certificate expires soon: $DAYS_UNTIL_EXPIRY days"
    fi
}

# Performance metrics
check_performance_metrics() {
    log_health "=== Performance Metrics ==="
    
    # Database performance
    DB_CONNECTIONS=$(mysql -h localhost -u "$DB_USER" -p"$DB_PASS" -e "SHOW STATUS LIKE 'Threads_connected';" | tail -1 | awk '{print $2}' 2>/dev/null || echo "0")
    log_health "Database connections: $DB_CONNECTIONS"
    
    # Redis performance
    REDIS_MEMORY=$(redis-cli info memory | grep used_memory_human | cut -d: -f2 | tr -d '\r' 2>/dev/null || echo "N/A")
    REDIS_KEYS=$(redis-cli dbsize 2>/dev/null || echo "0")
    log_health "Redis memory: $REDIS_MEMORY, Keys: $REDIS_KEYS"
    
    # PHP-FPM performance
    PHP_PROCESSES=$(pgrep -c php-fpm || echo "0")
    log_health "PHP-FPM processes: $PHP_PROCESSES"
}

# Main health check execution
main() {
    log_health "Starting comprehensive health check..."
    
    check_system_resources
    check_services
    check_application
    check_application_errors
    check_file_uploads
    check_ssl_certificate
    check_performance_metrics
    
    log_health "Health check completed"
    
    # Send alerts if any were logged
    if [ -s "$ALERT_LOG" ]; then
        tail -20 "$ALERT_LOG" | mail -s "Ableton Cookbook Health Alerts" admin@ableton.recipes 2>/dev/null || true
    fi
}

main "$@"
EOF
    
    chmod +x "$MONITORING_DIR/scripts/health-check.sh"
    chown deploy:deploy "$MONITORING_DIR/scripts/health-check.sh"
    
    log_info "Health check script created"
}

# Create backup script
create_backup_script() {
    log_info "Creating backup script..."
    
    cat << 'EOF' > "$MONITORING_DIR/scripts/backup.sh"
#!/bin/bash

# Ableton Cookbook Backup Script
# Automated backups of database, application files, and configurations

BACKUP_DIR="/var/www/ableton-cookbook/shared/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30
DB_USER="ableton_user"
DB_PASS="abletonCookbook2024!"
DB_NAME="ableton_cookbook"

log_backup() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "/var/log/ableton-cookbook/backup.log"
}

# Create backup directory
mkdir -p "$BACKUP_DIR"/{database,files,config}

log_backup "Starting backup process: $TIMESTAMP"

# Database backup
log_backup "Backing up database..."
mysqldump -h localhost -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$BACKUP_DIR/database/ableton_cookbook_$TIMESTAMP.sql.gz"

if [ $? -eq 0 ]; then
    log_backup "Database backup completed: ableton_cookbook_$TIMESTAMP.sql.gz"
else
    log_backup "ERROR: Database backup failed"
fi

# Application files backup (excluding storage and vendor)
log_backup "Backing up application files..."
tar -czf "$BACKUP_DIR/files/app_files_$TIMESTAMP.tar.gz" \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='.git' \
    -C /var/www/ableton-cookbook/current .

if [ $? -eq 0 ]; then
    log_backup "Application files backup completed: app_files_$TIMESTAMP.tar.gz"
else
    log_backup "ERROR: Application files backup failed"
fi

# Configuration backup
log_backup "Backing up configurations..."
tar -czf "$BACKUP_DIR/config/config_$TIMESTAMP.tar.gz" \
    /etc/nginx/sites-available/ableton-cookbook \
    /etc/php/8.2/fpm/pool.d/ableton-cookbook.conf \
    /etc/mysql/mysql.conf.d/mysqld.cnf \
    /etc/redis/redis.conf \
    /etc/supervisor/conf.d/ableton-cookbook.conf \
    /var/www/ableton-cookbook/shared/.env \
    2>/dev/null

if [ $? -eq 0 ]; then
    log_backup "Configuration backup completed: config_$TIMESTAMP.tar.gz"
else
    log_backup "ERROR: Configuration backup failed"
fi

# Storage directory backup (uploaded files)
log_backup "Backing up storage files..."
tar -czf "$BACKUP_DIR/files/storage_$TIMESTAMP.tar.gz" \
    -C /var/www/ableton-cookbook/shared/storage .

if [ $? -eq 0 ]; then
    log_backup "Storage backup completed: storage_$TIMESTAMP.tar.gz"
else
    log_backup "ERROR: Storage backup failed"
fi

# Cleanup old backups
log_backup "Cleaning up old backups (older than $RETENTION_DAYS days)..."
find "$BACKUP_DIR" -type f -mtime +$RETENTION_DAYS -delete
CLEANED=$(find "$BACKUP_DIR" -type f -mtime +$RETENTION_DAYS | wc -l)
log_backup "Cleaned up $CLEANED old backup files"

# Calculate backup sizes
DB_SIZE=$(du -sh "$BACKUP_DIR/database/ableton_cookbook_$TIMESTAMP.sql.gz" 2>/dev/null | awk '{print $1}' || echo "N/A")
FILES_SIZE=$(du -sh "$BACKUP_DIR/files/app_files_$TIMESTAMP.tar.gz" 2>/dev/null | awk '{print $1}' || echo "N/A")
STORAGE_SIZE=$(du -sh "$BACKUP_DIR/files/storage_$TIMESTAMP.tar.gz" 2>/dev/null | awk '{print $1}' || echo "N/A")
CONFIG_SIZE=$(du -sh "$BACKUP_DIR/config/config_$TIMESTAMP.tar.gz" 2>/dev/null | awk '{print $1}' || echo "N/A")

log_backup "Backup sizes - DB: $DB_SIZE, Files: $FILES_SIZE, Storage: $STORAGE_SIZE, Config: $CONFIG_SIZE"
log_backup "Backup process completed: $TIMESTAMP"
EOF
    
    chmod +x "$MONITORING_DIR/scripts/backup.sh"
    chown deploy:deploy "$MONITORING_DIR/scripts/backup.sh"
    
    log_info "Backup script created"
}

# Create maintenance script
create_maintenance_script() {
    log_info "Creating maintenance script..."
    
    cat << 'EOF' > "$MONITORING_DIR/scripts/maintenance.sh"
#!/bin/bash

# Ableton Cookbook Maintenance Script
# Regular maintenance tasks for optimal performance

MAINT_LOG="/var/log/ableton-cookbook/maintenance.log"

log_maint() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$MAINT_LOG"
}

log_maint "Starting maintenance tasks..."

# Clear application caches
log_maint "Clearing application caches..."
cd /var/www/ableton-cookbook/current
php artisan cache:clear
php artisan view:clear

# Optimize Laravel
log_maint "Optimizing Laravel application..."
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Clean up temporary files
log_maint "Cleaning up temporary files..."
find /var/www/ableton-cookbook/shared/storage/app/temp -type f -mmin +60 -delete 2>/dev/null || true
find /tmp -name "*.tmp" -mtime +1 -delete 2>/dev/null || true

# Clean up old quarantined files
log_maint "Cleaning up quarantined files (older than 7 days)..."
find /var/www/ableton-cookbook/shared/storage/app/quarantine -type f -mtime +7 -delete 2>/dev/null || true

# Update virus definitions
log_maint "Updating virus definitions..."
freshclam --quiet 2>/dev/null || log_maint "Failed to update virus definitions"

# Analyze database tables
log_maint "Analyzing database tables..."
mysql -h localhost -u ableton_user -p'abletonCookbook2024!' ableton_cookbook -e "ANALYZE TABLE users, racks, tags, rack_tags, rack_ratings, comments, rack_downloads;" 2>/dev/null || log_maint "Database analysis failed"

# Clean up Laravel logs (keep last 30 days)
log_maint "Cleaning up old Laravel logs..."
find /var/www/ableton-cookbook/current/storage/logs -name "*.log" -mtime +30 -delete 2>/dev/null || true

# Clean up Nginx logs (handled by logrotate, but check for oversized files)
NGINX_ACCESS_SIZE=$(du -m /var/log/nginx/access.log 2>/dev/null | awk '{print $1}' || echo "0")
if [ "$NGINX_ACCESS_SIZE" -gt 100 ]; then
    log_maint "WARNING: Large Nginx access log: ${NGINX_ACCESS_SIZE}MB"
fi

# Check and restart queue workers if needed
QUEUE_WORKERS=$(ps aux | grep -c "queue:work" || echo "0")
if [ "$QUEUE_WORKERS" -eq 0 ]; then
    log_maint "Restarting queue workers..."
    sudo supervisorctl restart ableton-cookbook:*
fi

# Restart PHP-FPM to clear memory leaks
log_maint "Restarting PHP-FPM for memory cleanup..."
sudo systemctl reload php8.2-fpm

# Generate statistics
TOTAL_RACKS=$(mysql -h localhost -u ableton_user -p'abletonCookbook2024!' ableton_cookbook -e "SELECT COUNT(*) FROM racks;" -N 2>/dev/null || echo "0")
TOTAL_USERS=$(mysql -h localhost -u ableton_user -p'abletonCookbook2024!' ableton_cookbook -e "SELECT COUNT(*) FROM users;" -N 2>/dev/null || echo "0")
DISK_USAGE=$(df -h / | awk 'NR==2 {print $5}')

log_maint "Statistics - Racks: $TOTAL_RACKS, Users: $TOTAL_USERS, Disk: $DISK_USAGE"
log_maint "Maintenance tasks completed"
EOF
    
    chmod +x "$MONITORING_DIR/scripts/maintenance.sh"
    chown deploy:deploy "$MONITORING_DIR/scripts/maintenance.sh"
    
    log_info "Maintenance script created"
}

# Create log analysis script
create_log_analysis_script() {
    log_info "Creating log analysis script..."
    
    cat << 'EOF' > "$MONITORING_DIR/scripts/log-analysis.sh"
#!/bin/bash

# Ableton Cookbook Log Analysis
# Analyzes application logs for insights and issues

ANALYSIS_LOG="/var/log/ableton-cookbook/log-analysis.log"
REPORT_FILE="/var/www/ableton-cookbook/shared/monitoring/reports/daily-report-$(date +%Y%m%d).txt"

log_analysis() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$ANALYSIS_LOG"
}

mkdir -p "$(dirname "$REPORT_FILE")"

log_analysis "Starting log analysis..."

{
    echo "Ableton Cookbook Daily Log Analysis Report"
    echo "Generated: $(date)"
    echo "================================================"
    echo ""

    # Nginx access log analysis
    echo "NGINX ACCESS LOG ANALYSIS"
    echo "-------------------------"
    
    ACCESS_LOG="/var/log/nginx/access.log"
    if [ -f "$ACCESS_LOG" ]; then
        echo "Total requests today: $(grep "$(date +%d/%b/%Y)" "$ACCESS_LOG" | wc -l)"
        echo "Unique visitors: $(grep "$(date +%d/%b/%Y)" "$ACCESS_LOG" | awk '{print $1}' | sort -u | wc -l)"
        echo ""
        echo "Top 10 requested pages:"
        grep "$(date +%d/%b/%Y)" "$ACCESS_LOG" | awk '{print $7}' | sort | uniq -c | sort -nr | head -10
        echo ""
        echo "Top 10 visitor IPs:"
        grep "$(date +%d/%b/%Y)" "$ACCESS_LOG" | awk '{print $1}' | sort | uniq -c | sort -nr | head -10
        echo ""
        echo "Response codes:"
        grep "$(date +%d/%b/%Y)" "$ACCESS_LOG" | awk '{print $9}' | sort | uniq -c | sort -nr
    else
        echo "No access log found"
    fi
    
    echo ""
    echo "NGINX ERROR LOG ANALYSIS"
    echo "------------------------"
    
    ERROR_LOG="/var/log/nginx/error.log"
    if [ -f "$ERROR_LOG" ]; then
        TODAY_ERRORS=$(grep "$(date +%Y/%m/%d)" "$ERROR_LOG" | wc -l)
        echo "Errors today: $TODAY_ERRORS"
        if [ "$TODAY_ERRORS" -gt 0 ]; then
            echo ""
            echo "Recent errors:"
            grep "$(date +%Y/%m/%d)" "$ERROR_LOG" | tail -5
        fi
    else
        echo "No error log found"
    fi
    
    echo ""
    echo "LARAVEL APPLICATION ANALYSIS"
    echo "---------------------------"
    
    LARAVEL_LOG="/var/www/ableton-cookbook/current/storage/logs/laravel-$(date +%Y-%m-%d).log"
    if [ -f "$LARAVEL_LOG" ]; then
        echo "Laravel log entries today: $(wc -l < "$LARAVEL_LOG")"
        echo "Errors: $(grep -c "ERROR" "$LARAVEL_LOG" || echo "0")"
        echo "Warnings: $(grep -c "WARNING" "$LARAVEL_LOG" || echo "0")"
        echo "Info messages: $(grep -c "INFO" "$LARAVEL_LOG" || echo "0")"
        
        if grep -q "ERROR" "$LARAVEL_LOG"; then
            echo ""
            echo "Recent errors:"
            grep "ERROR" "$LARAVEL_LOG" | tail -3
        fi
    else
        echo "No Laravel log for today"
    fi
    
    echo ""
    echo "UPLOAD ACTIVITY ANALYSIS"
    echo "-----------------------"
    
    UPLOAD_LOG="/var/log/ableton-cookbook/file-validation.log"
    if [ -f "$UPLOAD_LOG" ]; then
        UPLOADS_TODAY=$(grep "$(date +%Y-%m-%d)" "$UPLOAD_LOG" | grep -c "SUCCESS" || echo "0")
        FAILED_UPLOADS=$(grep "$(date +%Y-%m-%d)" "$UPLOAD_LOG" | grep -c "ERROR" || echo "0")
        echo "Successful uploads today: $UPLOADS_TODAY"
        echo "Failed uploads today: $FAILED_UPLOADS"
        
        if [ "$FAILED_UPLOADS" -gt 0 ]; then
            echo ""
            echo "Upload failure reasons:"
            grep "$(date +%Y-%m-%d)" "$UPLOAD_LOG" | grep "ERROR" | awk -F'ERROR: ' '{print $2}' | sort | uniq -c | sort -nr
        fi
    else
        echo "No upload log found"
    fi
    
    echo ""
    echo "SYSTEM RESOURCE TRENDS"
    echo "---------------------"
    
    PERF_LOG="/var/log/ableton-cookbook/performance.log"
    if [ -f "$PERF_LOG" ]; then
        echo "Average CPU usage today:"
        grep "$(date +%Y-%m-%d)" "$PERF_LOG" | grep "CPU:" | awk -F'CPU: ' '{print $2}' | awk -F'%' '{print $1}' | awk '{sum+=$1; count++} END {if(count>0) printf("%.1f%%\n", sum/count); else print "N/A"}'
        
        echo "Average memory usage today:"
        grep "$(date +%Y-%m-%d)" "$PERF_LOG" | grep "Memory:" | awk -F'Memory: ' '{print $2}' | awk -F'%' '{print $1}' | awk '{sum+=$1; count++} END {if(count>0) printf("%.1f%%\n", sum/count); else print "N/A"}'
        
        echo "Peak response time today:"
        grep "$(date +%Y-%m-%d)" "$PERF_LOG" | grep "response time:" | awk -F'response time: ' '{print $2}' | awk -F's' '{print $1}' | sort -nr | head -1
    else
        echo "No performance log found"
    fi
    
    echo ""
    echo "SECURITY EVENTS"
    echo "--------------"
    
    FAIL2BAN_LOG="/var/log/fail2ban.log"
    if [ -f "$FAIL2BAN_LOG" ]; then
        BANS_TODAY=$(grep "$(date +%Y-%m-%d)" "$FAIL2BAN_LOG" | grep -c "Ban" || echo "0")
        echo "IP bans today: $BANS_TODAY"
        
        if [ "$BANS_TODAY" -gt 0 ]; then
            echo "Banned IPs:"
            grep "$(date +%Y-%m-%d)" "$FAIL2BAN_LOG" | grep "Ban" | awk '{print $NF}' | sort | uniq -c | sort -nr
        fi
    else
        echo "No fail2ban log found"
    fi
    
    echo ""
    echo "================================================"
    echo "Report generated: $(date)"
    
} > "$REPORT_FILE"

log_analysis "Log analysis completed. Report saved to: $REPORT_FILE"

# Email the report
if [ -s "$REPORT_FILE" ]; then
    mail -s "Ableton Cookbook Daily Report - $(date +%Y-%m-%d)" admin@ableton.recipes < "$REPORT_FILE" 2>/dev/null || log_analysis "Failed to email report"
fi
EOF
    
    chmod +x "$MONITORING_DIR/scripts/log-analysis.sh"
    chown deploy:deploy "$MONITORING_DIR/scripts/log-analysis.sh"
    
    log_info "Log analysis script created"
}

# Configure Monit for service monitoring
configure_monit() {
    log_info "Configuring Monit for service monitoring..."
    
    # Create Monit configuration for Ableton Cookbook services
    sudo tee /etc/monit/conf.d/ableton-cookbook << 'EOF'
# Ableton Cookbook Service Monitoring

# Check Nginx
check process nginx with pidfile /var/run/nginx.pid
    group www
    start program = "/bin/systemctl start nginx"
    stop program = "/bin/systemctl stop nginx"
    if failed port 80 protocol http request "/" then restart
    if failed port 443 protocol https request "/" then restart
    if 5 restarts within 5 cycles then unmonitor

# Check PHP-FPM
check process php-fpm with pidfile /var/run/php/php8.2-fpm.pid
    group www
    start program = "/bin/systemctl start php8.2-fpm"
    stop program = "/bin/systemctl stop php8.2-fpm"
    if failed unixsocket /var/run/php/php8.2-fpm-ableton.sock then restart
    if 5 restarts within 5 cycles then unmonitor

# Check MySQL
check process mysql with pidfile /var/run/mysqld/mysqld.pid
    group database
    start program = "/bin/systemctl start mysql"
    stop program = "/bin/systemctl stop mysql"
    if failed port 3306 protocol mysql username "ableton_user" password "abletonCookbook2024!" then restart
    if 5 restarts within 5 cycles then unmonitor

# Check Redis
check process redis with pidfile /var/run/redis/redis-server.pid
    group cache
    start program = "/bin/systemctl start redis-server"
    stop program = "/bin/systemctl stop redis-server"
    if failed port 6379 protocol redis then restart
    if 5 restarts within 5 cycles then unmonitor

# Check Supervisor
check process supervisor with pidfile /var/run/supervisord.pid
    group workers
    start program = "/bin/systemctl start supervisor"
    stop program = "/bin/systemctl stop supervisor"
    if 5 restarts within 5 cycles then unmonitor

# Check filesystem usage
check filesystem rootfs with path /
    if space usage > 90% then alert
    if space usage > 95% then exec "/usr/bin/logger -t monit 'Critical disk space'"

# Check system resources
check system $HOST
    if loadavg (1min) > 4 then alert
    if loadavg (5min) > 2 then alert
    if cpu usage > 95% for 10 cycles then alert
    if memory usage > 90% then alert

# Check application response
check host ableton-cookbook with address ableton.recipes
    if failed port 443 protocol https request "/" then alert
    if response time > 10 seconds then alert

# Check SSL certificate
check program ssl-cert with path "/usr/bin/openssl s_client -connect ableton.recipes:443 -servername ableton.recipes < /dev/null 2>/dev/null | openssl x509 -noout -checkend 2592000"
    if status != 0 then alert
EOF
    
    # Configure Monit global settings
    sudo tee -a /etc/monit/monitrc << 'EOF'

# Ableton Cookbook Monit Configuration
set daemon 60
set logfile /var/log/monit.log
set idfile /var/lib/monit/id
set statefile /var/lib/monit/state

set eventqueue
    basedir /var/lib/monit/events
    slots 100

set mmonit http://localhost:8080/collector

set httpd port 2812 and
    use address localhost
    allow localhost
    allow admin:ableton2024!

set mailserver localhost

set alert admin@ableton.recipes

set mail-format {
    from: monit@ableton.recipes
    subject: $SERVICE $EVENT at $DATE
    message: $EVENT Service $SERVICE
                Date:        $DATE
                Action:      $ACTION
                Host:        $HOST
                Description: $DESCRIPTION
             
             Your faithful employee,
             Monit
}
EOF
    
    # Start and enable Monit
    sudo systemctl enable monit
    sudo systemctl start monit
    
    log_info "Monit configured and started"
}

# Setup cron jobs for automated tasks
setup_cron_jobs() {
    log_info "Setting up automated cron jobs..."
    
    # Create comprehensive crontab for deploy user
    (crontab -u deploy -l 2>/dev/null || true; cat << 'EOF'
# Ableton Cookbook Automated Tasks

# Health checks every 15 minutes
*/15 * * * * /var/www/ableton-cookbook/shared/monitoring/scripts/health-check.sh

# Performance monitoring every 5 minutes
*/5 * * * * /var/www/ableton-cookbook/shared/scripts/monitoring/performance-monitor.sh

# Cache warming every hour
0 * * * * /var/www/ableton-cookbook/shared/scripts/warm-cache.sh

# File cleanup every 6 hours
0 */6 * * * /var/www/ableton-cookbook/shared/scripts/cleanup-files.sh

# Daily maintenance at 2 AM
0 2 * * * /var/www/ableton-cookbook/shared/monitoring/scripts/maintenance.sh

# Daily backup at 3 AM
0 3 * * * /var/www/ableton-cookbook/shared/monitoring/scripts/backup.sh

# Daily log analysis at 11 PM
0 23 * * * /var/www/ableton-cookbook/shared/monitoring/scripts/log-analysis.sh

# Weekly database optimization (Sundays at 4 AM)
0 4 * * 0 mysql -h localhost -u ableton_user -p'abletonCookbook2024!' ableton_cookbook -e "OPTIMIZE TABLE users, racks, tags, rack_tags, rack_ratings, comments, rack_downloads;"

# Monthly virus definition update (1st of month at 5 AM)
0 5 1 * * /usr/bin/freshclam

EOF
) | crontab -u deploy -
    
    log_info "Cron jobs configured"
}

# Create dashboard script
create_dashboard() {
    log_info "Creating monitoring dashboard..."
    
    cat << 'EOF' > "$MONITORING_DIR/scripts/dashboard.sh"
#!/bin/bash

# Ableton Cookbook Monitoring Dashboard
# Real-time system overview

clear

echo "==============================================="
echo "    Ableton Cookbook Monitoring Dashboard"
echo "==============================================="
echo "Last updated: $(date)"
echo ""

# System Overview
echo "SYSTEM OVERVIEW"
echo "---------------"
echo "Uptime: $(uptime -p)"
echo "Load: $(uptime | awk -F'load average:' '{print $2}')"
echo "CPU: $(top -bn1 | grep "Cpu(s)" | awk '{print $2}') usage"
echo "Memory: $(free -h | grep Mem | awk '{printf("%.1f%% used (%s/%s)"), $3/$2*100, $3, $2}')"
echo "Disk: $(df -h / | awk 'NR==2 {printf("%s used (%s)", $5, $3)}')"
echo ""

# Services Status
echo "SERVICES STATUS"
echo "---------------"
services=("nginx" "php8.2-fpm" "mysql" "redis-server" "supervisor")
for service in "${services[@]}"; do
    if systemctl is-active --quiet "$service"; then
        echo "✅ $service: RUNNING"
    else
        echo "❌ $service: STOPPED"
    fi
done
echo ""

# Application Status
echo "APPLICATION STATUS"
echo "------------------"
response_code=$(curl -s -o /dev/null -w "%{http_code}" https://ableton.recipes)
response_time=$(curl -o /dev/null -s -w '%{time_total}' https://ableton.recipes)

if [ "$response_code" = "200" ]; then
    echo "✅ Website: ONLINE (${response_time}s)"
else
    echo "❌ Website: OFFLINE (HTTP $response_code)"
fi

# Database
if mysql -h localhost -u ableton_user -p'abletonCookbook2024!' -e "SELECT 1" ableton_cookbook >/dev/null 2>&1; then
    echo "✅ Database: CONNECTED"
    db_connections=$(mysql -h localhost -u ableton_user -p'abletonCookbook2024!' -e "SHOW STATUS LIKE 'Threads_connected';" | tail -1 | awk '{print $2}' 2>/dev/null)
    echo "   Active connections: $db_connections"
else
    echo "❌ Database: DISCONNECTED"
fi

# Redis
if redis-cli ping >/dev/null 2>&1; then
    echo "✅ Redis: CONNECTED"
    redis_keys=$(redis-cli dbsize)
    redis_memory=$(redis-cli info memory | grep used_memory_human | cut -d: -f2 | tr -d '\r')
    echo "   Keys: $redis_keys, Memory: $redis_memory"
else
    echo "❌ Redis: DISCONNECTED"
fi

# Queue Workers
queue_workers=$(ps aux | grep -c "queue:work")
if [ "$queue_workers" -gt 0 ]; then
    echo "✅ Queue Workers: $queue_workers running"
else
    echo "❌ Queue Workers: NONE"
fi

echo ""

# Recent Activity
echo "RECENT ACTIVITY (Last 24 hours)"
echo "--------------------------------"

# Uploads
uploads_today=$(grep "$(date +%Y-%m-%d)" /var/log/ableton-cookbook/file-validation.log 2>/dev/null | grep -c "SUCCESS" || echo "0")
failed_uploads=$(grep "$(date +%Y-%m-%d)" /var/log/ableton-cookbook/file-validation.log 2>/dev/null | grep -c "ERROR" || echo "0")
echo "Uploads: $uploads_today successful, $failed_uploads failed"

# Visitors
visitors_today=$(grep "$(date +%d/%b/%Y)" /var/log/nginx/access.log 2>/dev/null | awk '{print $1}' | sort -u | wc -l || echo "0")
requests_today=$(grep "$(date +%d/%b/%Y)" /var/log/nginx/access.log 2>/dev/null | wc -l || echo "0")
echo "Traffic: $visitors_today unique visitors, $requests_today requests"

# Errors
nginx_errors=$(grep "$(date +%Y/%m/%d)" /var/log/nginx/error.log 2>/dev/null | wc -l || echo "0")
laravel_errors=$(grep "$(date +%Y-%m-%d)" /var/www/ableton-cookbook/current/storage/logs/laravel-*.log 2>/dev/null | grep -c "ERROR" || echo "0")
echo "Errors: $nginx_errors nginx, $laravel_errors application"

echo ""
echo "==============================================="
echo "Press 'r' to refresh, 'q' to quit"

# Interactive mode
if [ "$1" = "--interactive" ]; then
    while true; do
        read -t 30 -n 1 key
        if [ "$key" = "q" ]; then
            break
        elif [ "$key" = "r" ] || [ -z "$key" ]; then
            exec "$0" --interactive
        fi
    done
fi
EOF
    
    chmod +x "$MONITORING_DIR/scripts/dashboard.sh"
    chown deploy:deploy "$MONITORING_DIR/scripts/dashboard.sh"
    
    # Create alias for easy access
    echo "alias dashboard='sudo -u deploy /var/www/ableton-cookbook/shared/monitoring/scripts/dashboard.sh --interactive'" | sudo tee -a /root/.bashrc
    
    log_info "Monitoring dashboard created"
}

# Generate monitoring setup report
generate_monitoring_report() {
    log_info "Generating monitoring setup report..."
    
    cat << EOF

🔍 Ableton Cookbook Monitoring & Maintenance Setup Complete!

Monitoring Components Installed:
✅ Comprehensive health checks (every 15 minutes)
✅ Performance monitoring (every 5 minutes)
✅ Automated backups (daily at 3 AM)
✅ Log analysis and reporting (daily at 11 PM)
✅ System maintenance (daily at 2 AM)
✅ Service monitoring with Monit
✅ Real-time dashboard
✅ Email alerting system

Key Monitoring Features:
- System resource monitoring (CPU, memory, disk)
- Service health checks (Nginx, PHP-FPM, MySQL, Redis)
- Application performance metrics
- SSL certificate expiration monitoring
- File upload system monitoring
- Security event tracking
- Automated maintenance tasks

Scripts Created:
- Health Check: $MONITORING_DIR/scripts/health-check.sh
- Backup: $MONITORING_DIR/scripts/backup.sh
- Maintenance: $MONITORING_DIR/scripts/maintenance.sh
- Log Analysis: $MONITORING_DIR/scripts/log-analysis.sh
- Dashboard: $MONITORING_DIR/scripts/dashboard.sh

Log Files:
- Health: /var/log/ableton-cookbook/health-check.log
- Alerts: /var/log/ableton-cookbook/alerts.log
- Performance: /var/log/ableton-cookbook/performance.log
- Backup: /var/log/ableton-cookbook/backup.log
- Maintenance: /var/log/ableton-cookbook/maintenance.log

Automated Tasks:
- Health checks: Every 15 minutes
- Performance monitoring: Every 5 minutes
- Cache warming: Every hour
- File cleanup: Every 6 hours
- Daily maintenance: 2:00 AM
- Daily backup: 3:00 AM
- Log analysis: 11:00 PM
- Weekly DB optimization: Sundays 4:00 AM

Monitoring Access:
- Monit Web Interface: http://localhost:2812 (admin:ableton2024!)
- Dashboard command: 'dashboard' (from root shell)
- Email reports: Sent to $ADMIN_EMAIL

Backup Retention:
- Database backups: 30 days
- File backups: 30 days
- Configuration backups: 30 days

Next Steps:
1. Configure external monitoring service (optional)
2. Set up offsite backup replication (recommended)
3. Configure SMS alerts for critical issues
4. Review and adjust alert thresholds based on traffic
5. Set up centralized logging with ELK stack (optional)

EOF
}

# Main execution
main() {
    log_info "🔍 Setting up monitoring and maintenance for Ableton Cookbook..."
    
    create_monitoring_structure
    install_monitoring_tools
    create_health_check_script
    create_backup_script
    create_maintenance_script
    create_log_analysis_script
    configure_monit
    setup_cron_jobs
    create_dashboard
    generate_monitoring_report
    
    log_info "🎉 Monitoring and maintenance setup completed successfully!"
}

# Run main function
main "$@"
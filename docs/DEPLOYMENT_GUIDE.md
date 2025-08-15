# Ableton Cookbook Laravel Production Deployment Guide

This comprehensive guide covers deploying the Ableton Cookbook Laravel Jetstream application to a production Ubuntu 22.04 server with complete security, performance optimization, and monitoring.

## Overview

The Ableton Cookbook is a sophisticated Laravel Jetstream application for sharing and managing Ableton Live rack files (.adg), featuring:

- **Laravel 12.0** with **Jetstream 5.3** (Livewire stack)
- **PHP 8.2** with advanced security features
- **File processing system** for .adg files with background job processing
- **Complex data model** with racks, users, ratings, comments, and collections
- **Real-time features** with queue processing and notifications
- **SEO optimization** with sitemaps and structured data

## Production Environment Specifications

- **Server**: Ubuntu 22.04 LTS
- **Resources**: 2 vCPUs, 4GB RAM, 60GB SSD
- **Domain**: ableton.recipes
- **IP**: 209.74.83.240
- **SSH Port**: 22022 (custom for security)

## Deployment Scripts Overview

The deployment includes 6 specialized scripts for different aspects of the production setup:

1. **`deployment/production-server-setup.sh`** - Base server configuration and service installation
2. **`deployment/database-migration.sh`** - SQLite to MySQL migration with data preservation
3. **`deployment/file-security-setup.sh`** - Secure file upload handling for .adg files
4. **`deployment/ssl-setup.sh`** - SSL certificate configuration with Let's Encrypt
5. **`deployment/performance-optimization.sh`** - Production performance tuning
6. **`deployment/monitoring-setup.sh`** - Comprehensive monitoring and maintenance automation

## Step-by-Step Deployment Process

### Phase 1: Server Preparation

#### 1.1 Initial Server Setup

First, ensure your Ubuntu 22.04 server is accessible via SSH on port 22022:

```bash
# Connect to your server
ssh -p 22022 root@209.74.83.240

# Update the system
apt update && apt upgrade -y

# Create deployment user with sudo privileges
adduser deploy
usermod -aG sudo deploy

# Configure SSH key authentication for the deploy user
mkdir -p /home/deploy/.ssh
# Copy your public key to /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys
```

#### 1.2 Transfer Deployment Scripts

Copy the deployment scripts to your server:

```bash
# From your local machine
scp -P 22022 -r deployment/ deploy@209.74.83.240:/tmp/
```

#### 1.3 Make Scripts Executable

```bash
# On the server as root
chmod +x /tmp/deployment/*.sh
```

### Phase 2: Core Infrastructure Setup

#### 2.1 Run Server Setup Script

```bash
# Execute as root
sudo /tmp/deployment/production-server-setup.sh
```

This script will:
- Install PHP 8.2 with all required extensions
- Configure Nginx with optimized settings
- Install and configure MySQL 8.0
- Set up Redis for caching and sessions
- Configure PHP-FPM with production settings
- Install Composer and Node.js
- Set up fail2ban and firewall
- Create application directory structure
- Configure supervisor for queue workers

**Expected Duration**: 15-20 minutes

#### 2.2 Verify Core Services

After the setup completes, verify all services are running:

```bash
sudo systemctl status nginx php8.2-fpm mysql redis-server supervisor
```

### Phase 3: Application Deployment

#### 3.1 Clone Repository

```bash
# Switch to deploy user
sudo su - deploy

# Clone your repository to the releases directory
cd /var/www/ableton-cookbook/releases
git clone https://github.com/your-username/ableton-cookbook.git $(date +%Y%m%d_%H%M%S)
cd $(ls -t | head -1)

# Install dependencies
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --only=production
npm run build
```

#### 3.2 Create Environment Configuration

```bash
# Create shared .env file
cp .env.example /var/www/ableton-cookbook/shared/.env

# Edit the environment file with production settings
nano /var/www/ableton-cookbook/shared/.env
```

Use these production environment settings:

```env
APP_NAME="Ableton Cookbook"
APP_ENV=production
APP_KEY=base64:GENERATE_NEW_KEY_HERE
APP_DEBUG=false
APP_URL=https://ableton.recipes

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ableton_cookbook
DB_USERNAME=ableton_user
DB_PASSWORD=abletonCookbook2024!

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_FROM_ADDRESS="noreply@ableton.recipes"
MAIL_FROM_NAME="${APP_NAME}"
```

#### 3.3 Generate Application Key

```bash
php artisan key:generate
```

#### 3.4 Create Symlinks

```bash
# Link shared directories
ln -nfs /var/www/ableton-cookbook/shared/storage storage
ln -nfs /var/www/ableton-cookbook/shared/.env .env

# Set up current release symlink
ln -nfs $(pwd) /var/www/ableton-cookbook/current
```

### Phase 4: Database Migration

#### 4.1 Run Database Migration Script

```bash
# Execute as deploy user
/tmp/deployment/database-migration.sh
```

This script will:
- Create comprehensive backups of your SQLite database
- Export and convert SQLite data to MySQL format
- Execute the migration with data integrity verification
- Update Laravel configuration for MySQL
- Create rollback scripts for emergency recovery

**Expected Duration**: 10-15 minutes (depends on data size)

#### 4.2 Verify Database Migration

```bash
# Test database connectivity
php artisan migrate:status

# Verify data integrity
mysql -h localhost -u ableton_user -p'abletonCookbook2024!' ableton_cookbook -e "
SELECT 'Users' as table_name, COUNT(*) as count FROM users
UNION ALL
SELECT 'Racks' as table_name, COUNT(*) as count FROM racks
UNION ALL
SELECT 'Comments' as table_name, COUNT(*) as count FROM comments;"
```

### Phase 5: File Security Configuration

#### 5.1 Run File Security Setup

```bash
# Execute as root
sudo /tmp/deployment/file-security-setup.sh
```

This script will:
- Install ClamAV antivirus scanner
- Create secure file validation pipelines
- Set up quarantine directories with proper permissions
- Configure automated file cleanup
- Implement upload monitoring and alerting
- Create Laravel validation rules for .adg files

**Expected Duration**: 10-15 minutes

#### 5.2 Test File Upload Security

```bash
# Verify ClamAV is running
sudo systemctl status clamav-daemon

# Check upload directories
ls -la /var/www/ableton-cookbook/shared/storage/app/
```

### Phase 6: SSL Certificate Configuration

#### 6.1 Configure Domain DNS

Before running SSL setup, ensure your domain points to the server:

```bash
# Test DNS resolution
nslookup ableton.recipes
dig ableton.recipes A
```

#### 6.2 Run SSL Setup Script

```bash
# Execute as root
sudo /tmp/deployment/ssl-setup.sh
```

This script will:
- Install Certbot for Let's Encrypt
- Obtain SSL certificates for ableton.recipes and www.ableton.recipes
- Configure Nginx with production SSL settings
- Set up automatic certificate renewal
- Implement security headers and HSTS
- Configure rate limiting and security policies

**Expected Duration**: 5-10 minutes

#### 6.3 Verify SSL Configuration

```bash
# Test HTTPS access
curl -I https://ableton.recipes

# Check SSL certificate details
openssl s_client -connect ableton.recipes:443 -servername ableton.recipes < /dev/null 2>/dev/null | openssl x509 -noout -dates

# Test SSL rating (optional)
# Visit: https://www.ssllabs.com/ssltest/analyze.html?d=ableton.recipes
```

### Phase 7: Performance Optimization

#### 7.1 Run Performance Optimization Script

```bash
# Execute as root
sudo /tmp/deployment/performance-optimization.sh
```

This script will:
- Optimize PHP-FPM for high traffic (dynamic process management)
- Configure MySQL for SSD storage and read-heavy workloads
- Optimize Redis for caching and session storage
- Apply Laravel production optimizations
- Tune system kernel parameters
- Set up log rotation and cleanup
- Create cache warming automation

**Expected Duration**: 5-10 minutes

#### 7.2 Verify Performance Settings

```bash
# Check PHP-FPM pool status
sudo systemctl status php8.2-fpm

# Verify MySQL settings
mysql -h localhost -u ableton_user -p'abletonCookbook2024!' -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"

# Test Redis performance
redis-cli info memory
```

### Phase 8: Monitoring and Maintenance Setup

#### 8.1 Run Monitoring Setup Script

```bash
# Execute as root
sudo /tmp/deployment/monitoring-setup.sh
```

This script will:
- Install comprehensive monitoring tools (htop, iotop, monit)
- Configure health checks for all services
- Set up automated backup system (daily at 3 AM)
- Create log analysis and reporting
- Configure email alerting for critical issues
- Set up performance monitoring dashboard
- Implement automated maintenance tasks

**Expected Duration**: 15-20 minutes

#### 8.2 Verify Monitoring Setup

```bash
# Check Monit status
sudo systemctl status monit

# Access monitoring dashboard
dashboard

# Verify cron jobs
crontab -u deploy -l

# Check monitoring logs
ls -la /var/log/ableton-cookbook/
```

### Phase 9: GitHub Actions CI/CD Setup

#### 9.1 Configure GitHub Secrets

In your GitHub repository, add these secrets:

1. **DEPLOY_SSH_KEY**: Private SSH key for the deploy user
2. **SERVER_HOST**: 209.74.83.240
3. **SERVER_PORT**: 22022
4. **SERVER_USER**: deploy

#### 9.2 Test Automated Deployment

The GitHub Actions workflow (`.github/workflows/deploy.yml`) will automatically:
- Run tests on every push to main
- Perform security scans
- Deploy to production on successful tests
- Run health checks post-deployment

To test the pipeline:

```bash
# Make a small change and push to main
git add .
git commit -m "Test deployment pipeline"
git push origin main
```

## Post-Deployment Verification

### Application Health Check

```bash
# Test main application endpoints
curl -I https://ableton.recipes
curl -I https://ableton.recipes/upload
curl -I https://ableton.recipes/sitemap.xml

# Verify database connectivity
php artisan tinker
>>> DB::connection()->getPdo()
>>> exit

# Check queue workers
ps aux | grep queue:work

# Verify file upload functionality
# (Test through the web interface)
```

### Performance Baseline

```bash
# Test response times
curl -o /dev/null -s -w '%{time_total}' https://ableton.recipes

# Check resource usage
htop
free -h
df -h
```

### Security Verification

```bash
# Test SSL configuration
testssl.sh ableton.recipes

# Verify firewall rules
sudo ufw status

# Check fail2ban status
sudo fail2ban-client status

# Test file upload security
# (Attempt to upload non-.adg files through the interface)
```

## Daily Operations

### Monitoring

- **Dashboard**: Run `dashboard` command for real-time status
- **Logs**: Check `/var/log/ableton-cookbook/` for all application logs
- **Monit**: Access http://localhost:2812 (admin:ableton2024!)
- **Email Reports**: Daily reports sent to admin@ableton.recipes

### Maintenance

All maintenance tasks are automated via cron jobs:

- **Health checks**: Every 15 minutes
- **Performance monitoring**: Every 5 minutes
- **Cache warming**: Every hour
- **File cleanup**: Every 6 hours
- **Daily maintenance**: 2:00 AM
- **Daily backup**: 3:00 AM
- **Log analysis**: 11:00 PM

### Manual Operations

```bash
# Laravel optimization
sudo -u deploy /var/www/ableton-cookbook/shared/scripts/optimize-laravel.sh

# Manual backup
sudo -u deploy /var/www/ableton-cookbook/shared/monitoring/scripts/backup.sh

# Check application health
sudo -u deploy /var/www/ableton-cookbook/shared/monitoring/scripts/health-check.sh

# View performance metrics
tail -f /var/log/ableton-cookbook/performance.log
```

## Troubleshooting

### Common Issues

#### 1. Application Returns 500 Error

```bash
# Check Laravel logs
tail -f /var/www/ableton-cookbook/current/storage/logs/laravel-$(date +%Y-%m-%d).log

# Check PHP-FPM logs
tail -f /var/log/php8.2-fpm.log

# Verify permissions
ls -la /var/www/ableton-cookbook/current/storage/
```

#### 2. Database Connection Issues

```bash
# Test MySQL connectivity
mysql -h localhost -u ableton_user -p'abletonCookbook2024!' ableton_cookbook -e "SELECT 1;"

# Check MySQL status
sudo systemctl status mysql

# Review MySQL logs
sudo tail -f /var/log/mysql/error.log
```

#### 3. File Upload Problems

```bash
# Check upload directory permissions
ls -la /var/www/ableton-cookbook/shared/storage/app/private/

# Verify ClamAV status
sudo systemctl status clamav-daemon

# Check quarantine directory
ls -la /var/www/ableton-cookbook/shared/storage/app/quarantine/

# Review upload logs
tail -f /var/log/ableton-cookbook/file-validation.log
```

#### 4. Performance Issues

```bash
# Check system resources
htop
iotop -o

# Analyze slow queries
sudo tail -f /var/log/mysql/mysql-slow.log

# Check Redis performance
redis-cli --latency-history

# Review performance logs
tail -f /var/log/ableton-cookbook/performance.log
```

### Emergency Procedures

#### Rollback to Previous Release

```bash
# List available releases
ls -la /var/www/ableton-cookbook/releases/

# Rollback to previous release
cd /var/www/ableton-cookbook/releases
PREVIOUS_RELEASE=$(ls -t | sed -n '2p')
ln -nfs /var/www/ableton-cookbook/releases/$PREVIOUS_RELEASE /var/www/ableton-cookbook/current

# Reload services
sudo systemctl reload php8.2-fpm nginx
```

#### Database Rollback

```bash
# Use the automatically created rollback script
/var/www/ableton-cookbook/shared/backups/rollback_to_sqlite_TIMESTAMP.sh
```

#### Service Recovery

```bash
# Restart all services
sudo systemctl restart nginx php8.2-fpm mysql redis-server supervisor

# Check service status
sudo systemctl status nginx php8.2-fpm mysql redis-server supervisor
```

## Security Best Practices

### Regular Security Tasks

1. **Monitor Security Logs**: Check fail2ban and auth logs daily
2. **Update System**: Apply security updates monthly
3. **SSL Certificate**: Monitor expiration (automated)
4. **Backup Verification**: Test restore procedures monthly
5. **Access Review**: Audit SSH access and user permissions quarterly

### Security Monitoring

```bash
# Check failed login attempts
sudo tail -f /var/log/auth.log

# Review fail2ban activity
sudo fail2ban-client status sshd

# Monitor suspicious file uploads
tail -f /var/log/ableton-cookbook/file-validation.log | grep ERROR
```

## Performance Optimization

### Load Testing

Before going live, perform load testing:

```bash
# Install testing tools
sudo apt install apache2-utils

# Basic load test
ab -n 1000 -c 10 https://ableton.recipes/

# Test file upload endpoint
# (Create custom load test for upload functionality)
```

### Scaling Considerations

For higher traffic, consider:

1. **Database Optimization**: Read replicas, connection pooling
2. **Caching Layer**: Implement Redis clustering
3. **Load Balancing**: Multiple application servers
4. **CDN Integration**: Static asset delivery
5. **Queue Scaling**: Multiple queue workers

## Backup and Recovery

### Backup Locations

- **Database**: `/var/www/ableton-cookbook/shared/backups/database/`
- **Files**: `/var/www/ableton-cookbook/shared/backups/files/`
- **Configuration**: `/var/www/ableton-cookbook/shared/backups/config/`

### Recovery Procedures

#### Database Recovery

```bash
# Restore from backup
gunzip -c /var/www/ableton-cookbook/shared/backups/database/ableton_cookbook_TIMESTAMP.sql.gz | mysql -h localhost -u ableton_user -p'abletonCookbook2024!' ableton_cookbook
```

#### File Recovery

```bash
# Restore application files
cd /var/www/ableton-cookbook/releases
tar -xzf /var/www/ableton-cookbook/shared/backups/files/app_files_TIMESTAMP.tar.gz

# Restore storage files
cd /var/www/ableton-cookbook/shared
tar -xzf /var/www/ableton-cookbook/shared/backups/files/storage_TIMESTAMP.tar.gz
```

## Support and Maintenance

### Log Files Reference

| Log File | Purpose | Location |
|----------|---------|----------|
| Application | Laravel errors and info | `/var/www/ableton-cookbook/current/storage/logs/` |
| Health Checks | System health monitoring | `/var/log/ableton-cookbook/health-check.log` |
| Performance | Resource usage metrics | `/var/log/ableton-cookbook/performance.log` |
| File Uploads | Upload validation results | `/var/log/ableton-cookbook/file-validation.log` |
| Backups | Backup operation status | `/var/log/ableton-cookbook/backup.log` |
| Nginx Access | Web server requests | `/var/log/nginx/access.log` |
| Nginx Error | Web server errors | `/var/log/nginx/error.log` |
| MySQL Slow | Database slow queries | `/var/log/mysql/mysql-slow.log` |
| PHP-FPM | PHP process errors | `/var/log/php8.2-fpm.log` |

### Key Directories

| Directory | Purpose |
|-----------|---------|
| `/var/www/ableton-cookbook/current` | Current application release |
| `/var/www/ableton-cookbook/releases` | All application releases |
| `/var/www/ableton-cookbook/shared` | Shared files and configurations |
| `/var/www/ableton-cookbook/shared/storage` | User uploads and app storage |
| `/var/www/ableton-cookbook/shared/monitoring` | Monitoring scripts and reports |

This deployment guide provides a complete, production-ready setup for the Ableton Cookbook application with enterprise-level security, performance, and monitoring capabilities.
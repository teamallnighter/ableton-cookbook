# D2 Diagram Service - Production Deployment Guide

## Overview
The Ableton Cookbook uses D2 (Declarative Diagramming) to generate visual representations of Ableton Live rack signal flows. This guide covers deploying D2 to Ubuntu production servers.

## Architecture
```
Laravel App → D2DiagramService → D2 CLI → SVG/ASCII Output
                      ↓
                Redis Cache
```

## Local Development (macOS)

### Installation
```bash
# Install D2 via Homebrew
brew install d2

# Verify installation
d2 --version

# Test health check
php artisan d2:health
```

### Configuration (.env)
```env
# Local development - uses system PATH
D2_ENABLED=true
D2_USE_SYSTEM_PATH=true
D2_CACHE_ENABLED=false  # Optional for development
```

## Production Deployment (Ubuntu)

### Quick Deploy
```bash
# 1. Run the deployment script
sudo bash laravel-app/scripts/deploy-d2-ubuntu.sh

# 2. Add to .env file
D2_ENABLED=true
D2_BINARY_PATH=/usr/local/bin/d2
D2_TEMP_PATH=/var/www/temp/d2
D2_TIMEOUT=10
D2_CACHE_ENABLED=true
D2_CACHE_TTL=3600
D2_USE_SYSTEM_PATH=false

# 3. Clear config cache
php artisan config:clear
php artisan config:cache

# 4. Run health check
php artisan d2:health --verbose
```

### Manual Installation

#### 1. Install D2
```bash
# Download latest release
LATEST_VERSION=$(curl -s https://api.github.com/repos/terrastruct/d2/releases/latest | grep tag_name | cut -d '"' -f 4)
wget "https://github.com/terrastruct/d2/releases/download/${LATEST_VERSION}/d2-${LATEST_VERSION}-linux-amd64.tar.gz"

# Extract and install
tar -xzf d2-*.tar.gz
sudo mv d2 /usr/local/bin/
sudo chmod +x /usr/local/bin/d2

# Verify
d2 --version
```

#### 2. Configure Permissions
```bash
# Test as web server user
sudo -u www-data /usr/local/bin/d2 --version

# Create temp directory
sudo mkdir -p /var/www/temp/d2
sudo chown www-data:www-data /var/www/temp/d2
```

#### 3. Configure Laravel
Add to `.env`:
```env
D2_ENABLED=true
D2_BINARY_PATH=/usr/local/bin/d2
D2_TEMP_PATH=/var/www/temp/d2
D2_USE_SYSTEM_PATH=false
D2_TIMEOUT=10
D2_CACHE_ENABLED=true
D2_CACHE_TTL=3600
```

## Docker Deployment

### Dockerfile
```dockerfile
FROM php:8.2-fpm

# Install D2
RUN apt-get update && apt-get install -y wget tar \
    && wget https://github.com/terrastruct/d2/releases/latest/download/d2-linux-amd64.tar.gz \
    && tar -xzf d2-linux-amd64.tar.gz \
    && mv d2 /usr/local/bin/ \
    && chmod +x /usr/local/bin/d2 \
    && rm d2-linux-amd64.tar.gz

# Create temp directory
RUN mkdir -p /var/www/temp/d2 \
    && chown www-data:www-data /var/www/temp/d2

# Your Laravel app setup continues...
```

## Health Monitoring

### Artisan Command
```bash
# Basic health check
php artisan d2:health

# Verbose output with configuration details
php artisan d2:health --verbose
```

### Automated Monitoring
Add to crontab for regular checks:
```cron
# Check D2 health every hour
0 * * * * cd /var/www/your-app && php artisan d2:health >> storage/logs/d2-health.log 2>&1
```

### Manual Health Check Script
```bash
#!/bin/bash
# /usr/local/bin/check-d2-health

# Check D2 binary
if ! /usr/local/bin/d2 --version >/dev/null 2>&1; then
    echo "ERROR: D2 not responding"
    exit 1
fi

# Check www-data permissions
if ! sudo -u www-data /usr/local/bin/d2 --version >/dev/null 2>&1; then
    echo "ERROR: www-data cannot execute D2"
    exit 1
fi

echo "D2 is healthy"
exit 0
```

## Performance Optimization

### 1. Redis Caching
D2 diagrams are automatically cached when Redis is configured:
```env
CACHE_DRIVER=redis
D2_CACHE_ENABLED=true
D2_CACHE_TTL=3600  # 1 hour
```

### 2. Queue Processing (Future Enhancement)
For complex diagrams, consider queue processing:
```php
// Future implementation
dispatch(new GenerateD2DiagramJob($rackData));
```

### 3. Timeout Configuration
Adjust based on diagram complexity:
```env
D2_TIMEOUT=10  # Default 10 seconds
# Increase for complex diagrams:
D2_TIMEOUT=30
```

## Troubleshooting

### Common Issues

#### 1. D2 Command Not Found
```bash
# Check PATH
echo $PATH

# Use absolute path in .env
D2_BINARY_PATH=/usr/local/bin/d2
```

#### 2. Permission Denied
```bash
# Fix permissions
sudo chmod 755 /usr/local/bin/d2
sudo chown www-data:www-data /var/www/temp/d2
```

#### 3. Timeout Errors
```bash
# Increase timeout in .env
D2_TIMEOUT=30

# Check system resources
free -h
top
```

#### 4. Cache Issues
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

### Debug Mode
Enable detailed logging:
```env
D2_LOGGING_ENABLED=true
D2_LOG_LEVEL=debug
```

Check logs:
```bash
tail -f storage/logs/laravel.log | grep D2
```

## Security Considerations

1. **Input Sanitization**: D2DiagramService sanitizes all input device names
2. **Timeout Protection**: Commands have timeout to prevent DOS
3. **Temp File Cleanup**: Automatic cleanup of temporary files
4. **Rate Limiting**: Configured per-user limits in `config/d2.php`

## API Usage

### Generate Diagram
```php
use App\Services\D2DiagramService;

$d2Service = app(D2DiagramService::class);

// Check availability
if ($d2Service->isAvailable()) {
    // Generate D2 code
    $d2Code = $d2Service->generateRackDiagram($rackData);

    // Render to SVG
    $svg = $d2Service->renderDiagram($d2Code, 'svg');

    // Render to ASCII
    $ascii = $d2Service->renderDiagram($d2Code, 'ascii');
}
```

## Monitoring & Metrics

### Key Metrics to Track
- D2 command execution time
- Cache hit/miss ratio
- Timeout frequency
- Error rate by type

### Example Monitoring Setup
```php
// In D2DiagramService
Log::info('D2 diagram generated', [
    'rack_uuid' => $rackData['uuid'],
    'format' => $format,
    'execution_time' => $executionTime,
    'cache_hit' => $cacheHit,
]);
```

## Rollback Plan

If D2 integration causes issues:

1. **Disable D2 quickly**:
```env
D2_ENABLED=false
```

2. **Clear caches**:
```bash
php artisan config:clear
```

3. **Fallback to text-only**:
The application will gracefully degrade when D2 is disabled.

## Support

- **D2 Documentation**: https://d2lang.com/
- **GitHub Issues**: https://github.com/terrastruct/d2/issues
- **Laravel Logs**: `storage/logs/laravel.log`

## Checklist for Production

- [ ] D2 binary installed at `/usr/local/bin/d2`
- [ ] www-data can execute D2
- [ ] Temp directory created and writable
- [ ] Environment variables configured
- [ ] Redis cache configured
- [ ] Health check passing
- [ ] Monitoring in place
- [ ] Rollback plan documented
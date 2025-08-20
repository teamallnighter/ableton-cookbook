# 🚀 Ableton Cookbook - Quick Reference

## Essential Commands

### Check System Status
```bash
# All services status
systemctl status nginx php8.2-fpm mysql redis-server supervisor

# Queue workers
supervisorctl status

# Test site
curl -I https://ableton.recipes
```

### Application Maintenance
```bash
cd /var/www/ableton-cookbook/laravel-app

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# View logs
tail -f storage/logs/laravel.log

# Restart queue workers
supervisorctl restart ableton-cookbook-worker:*
```

### SSL Certificate
```bash
# Check certificate
certbot certificates

# Test renewal
certbot renew --dry-run
```

## Key File Locations
- **App:** `/var/www/ableton-cookbook/laravel-app/`
- **Nginx Config:** `/etc/nginx/sites-available/ableton-cookbook`
- **Environment:** `/var/www/ableton-cookbook/laravel-app/.env`
- **Logs:** `/var/log/nginx/` and `storage/logs/`

## Deployed: August 16, 2025
**Live at: https://ableton.recipes** 🎵

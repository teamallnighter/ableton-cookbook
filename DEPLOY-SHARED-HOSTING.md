# Deploying Ableton Cookbook to Shared Hosting

## Prerequisites
- PHP 8.4+ ✅
- MySQL database ✅
- SSL certificate ✅
- Composer access (or pre-built files)
- File manager or FTP access

## Step 1: Prepare Files Locally

```bash
# Make deployment script executable
chmod +x shared-hosting-deploy.sh

# Run the deployment preparation
./shared-hosting-deploy.sh
```

This creates `ableton-cookbook-deploy.tar.gz` with optimized production files.

## Step 2: Database Setup

1. Create a MySQL database in your hosting control panel
2. Note down:
   - Database name
   - Database username
   - Database password
   - Database host (usually localhost)

## Step 3: Upload Files

1. Upload `ableton-cookbook-deploy.tar.gz` to your hosting
2. Extract it in your web root:
   ```bash
   tar -xzf ableton-cookbook-deploy.tar.gz
   mv laravel-app/* .
   mv laravel-app/.[^.]* .  # Move hidden files
   rmdir laravel-app
   ```

3. Point your domain's document root to the `public/` directory

## Step 4: Configure Environment

1. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```

2. Edit `.env` with your production settings:
   ```env
   APP_NAME="Ableton Cookbook"
   APP_ENV=production
   APP_KEY=
   APP_DEBUG=false
   APP_URL=https://yourdomain.com

   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=your_database_name
   DB_USERNAME=your_database_user
   DB_PASSWORD=your_database_password

   MAIL_MAILER=smtp
   MAIL_HOST=mail.yourdomain.com
   MAIL_PORT=587
   MAIL_USERNAME=your_email@yourdomain.com
   MAIL_PASSWORD=your_email_password
   ```

3. Generate application key:
   ```bash
   php artisan key:generate
   ```

## Step 5: Database Migration

Run migrations through SSH or create a temporary route:

### Option A: SSH Access
```bash
php artisan migrate --force
php artisan db:seed --force  # Optional: add sample data
```

### Option B: Web-based Migration (temporary)
Add to `routes/web.php` temporarily:
```php
Route::get('/run-migrations', function () {
    if (app()->environment('production')) {
        Artisan::call('migrate', ['--force' => true]);
        return 'Migrations completed';
    }
});
```
Visit: `https://yourdomain.com/run-migrations`
**Remove this route immediately after!**

## Step 6: Set Permissions

```bash
# Storage directories need write permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Ensure web server owns these directories
chown -R www-data:www-data storage
chown -R www-data:www-data bootstrap/cache
```

Or through FTP: Set folders to 775 and files to 664.

## Step 7: Configure .htaccess

Ensure `public/.htaccess` exists with:
```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Force HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# Security Headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/css text/javascript application/javascript application/json
</IfModule>
```

## Step 8: Queue Worker Setup

For background jobs (rack processing), set up a cron job:

```bash
# Add to crontab (every minute)
* * * * * cd /path/to/your/app && php artisan schedule:run >> /dev/null 2>&1

# Process queue jobs every 5 minutes
*/5 * * * * cd /path/to/your/app && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

## Step 9: Email Configuration

Configure your domain's email in `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
```

## Step 10: Final Checks

1. **Test the application**: Visit your domain
2. **Check logs**: `storage/logs/laravel.log` for any errors
3. **Test file uploads**: Try uploading a rack file
4. **Test email**: Register a new account
5. **Monitor performance**: Check page load times

## Optimization Tips

### Enable OPcache
Add to `.htaccess` or `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
```

### Database Optimization
```bash
php artisan optimize:clear
php artisan optimize
```

### CDN Integration
Update `.env`:
```env
ASSET_URL=https://cdn.yourdomain.com
```

## Troubleshooting

### 500 Internal Server Error
- Check `.env` file exists and has correct values
- Verify file permissions (storage needs write access)
- Check PHP version is 8.4+
- Review `storage/logs/laravel.log`

### 404 on All Routes
- Ensure document root points to `public/` directory
- Check `.htaccess` file exists in `public/`
- Verify mod_rewrite is enabled

### File Uploads Not Working
- Check `storage/app/` is writable
- Verify PHP upload limits in `php.ini`:
  ```ini
  upload_max_filesize = 50M
  post_max_size = 50M
  ```

### Slow Performance
- Enable caching: `php artisan optimize`
- Use database for sessions/cache (already configured)
- Enable OPcache
- Consider CDN for assets

## Security Checklist

- [ ] APP_DEBUG=false in production
- [ ] Strong APP_KEY generated
- [ ] HTTPS forced
- [ ] Database credentials secure
- [ ] File permissions properly set
- [ ] Directory listing disabled
- [ ] Error reporting disabled in production
- [ ] Regular backups configured

## Maintenance Mode

To enable maintenance mode:
```bash
php artisan down --message="Upgrading Database" --retry=60
```

To disable:
```bash
php artisan up
```

## Backup Strategy

Regular backups should include:
1. Database (via phpMyAdmin or mysqldump)
2. `storage/app/` directory (uploaded racks)
3. `.env` file
4. Any custom configurations

## Support

For issues specific to shared hosting deployment, check:
- PHP error logs in your hosting control panel
- Laravel logs in `storage/logs/`
- Web server error logs
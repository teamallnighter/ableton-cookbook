# Ableton Cookbook VM Deployment Steps

After running `deploy-to-vm.sh`, follow these steps:

## Step 1: Secure MySQL
```bash
sudo mysql_secure_installation
# Answer: Y, Y, Y, Y, Y (set strong root password)
```

## Step 2: Create Database & User
```bash
# Log into MySQL as root
sudo mysql

# Run these MySQL commands:
CREATE DATABASE ableton_cookbook;
CREATE USER 'ableton_user'@'localhost' IDENTIFIED BY 'YourSecurePassword123!';
GRANT ALL PRIVILEGES ON ableton_cookbook.* TO 'ableton_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## Step 3: Deploy Application
```bash
# Clone your repository
cd /var/www/ableton-cookbook
sudo git clone https://github.com/YOUR-USERNAME/abletonCookbookPHP.git .

# Navigate to Laravel app
cd laravel-app

# Install dependencies
sudo composer install --no-dev --optimize-autoloader
sudo npm install
sudo npm run build

# Set up environment
sudo cp .env.production-template .env
sudo nano .env  # Edit with your settings

# Generate application key
sudo php artisan key:generate

# Run migrations
sudo php artisan migrate --force

# Create storage link
sudo php artisan storage:link

# Optimize for production
sudo php artisan config:cache
sudo php artisan route:cache
sudo php artisan view:cache

# Set permissions
sudo chown -R www-data:www-data /var/www/ableton-cookbook
sudo chmod -R 755 /var/www/ableton-cookbook
sudo chmod -R 775 /var/www/ableton-cookbook/laravel-app/storage
sudo chmod -R 775 /var/www/ableton-cookbook/laravel-app/bootstrap/cache
```

## Step 4: Configure Nginx
```bash
# Remove default site
sudo rm /etc/nginx/sites-enabled/default

# Create new site configuration
sudo nano /etc/nginx/sites-available/ableton-cookbook

# Copy the nginx-config.conf content here, then:
sudo ln -s /etc/nginx/sites-available/ableton-cookbook /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

## Step 5: Set up Queue Workers
```bash
# Create supervisor config
sudo nano /etc/supervisor/conf.d/ableton-cookbook-worker.conf

# Copy the supervisor-config.conf content here, then:
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start ableton-cookbook-worker:*
```

## Step 6: SSL with Let's Encrypt (Optional)
```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Get SSL certificate (replace your-domain.com)
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

## Step 7: Test Everything
```bash
# Check services
sudo systemctl status nginx php8.2-fpm mysql redis-server
sudo supervisorctl status

# Test application
curl -I http://your-server-ip
```

## Important Environment Variables to Set in .env:
- `APP_URL=https://your-domain.com`
- `DB_PASSWORD=YourSecurePassword123!`
- Your SMTP settings for email
- Set `APP_DEBUG=false` for production

## Maintenance Commands:
```bash
# View application logs
sudo tail -f /var/www/ableton-cookbook/laravel-app/storage/logs/laravel.log

# View queue worker logs  
sudo tail -f /var/www/ableton-cookbook/laravel-app/storage/logs/worker.log

# Restart queue workers
sudo supervisorctl restart ableton-cookbook-worker:*

# Clear cache
cd /var/www/ableton-cookbook/laravel-app
sudo php artisan cache:clear
sudo php artisan config:clear
```

#!/bin/bash

# 🎵 Ableton Cookbook - Production Deployment Script
# This script handles complete deployment to production server

set -e  # Exit on any error

echo "🎵 Starting Ableton Cookbook Production Deployment..."

# Configuration
REPO_URL="https://github.com/teamallnighter/ableton-cookbook.git"
DEPLOY_PATH="/var/www/ableton-cookbook"
APP_PATH="$DEPLOY_PATH/laravel-app"
DOMAIN="ableton.recipes"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check if running as root (needed for some operations)
if [ "$EUID" -ne 0 ]; then
    print_error "Please run as root (use sudo)"
    exit 1
fi

print_status "Phase 1: Environment Setup"

# Navigate to application directory
cd $APP_PATH

print_status "Phase 2: Code Updates"

# Pull latest code
git fetch --all
git reset --hard origin/main
git pull origin main

print_status "Phase 3: Dependencies & Environment"

# Set composer to allow running as superuser
export COMPOSER_ALLOW_SUPERUSER=1

# Install/Update PHP dependencies
# First install with dev dependencies to ensure all packages are present
composer install --optimize-autoloader --no-interaction

# Then remove dev dependencies for production
composer install --no-dev --optimize-autoloader --no-interaction

# Copy and configure environment file
if [ ! -f .env ]; then
    cp .env.production .env
    print_warning "Created .env from .env.production template - PLEASE UPDATE CREDENTIALS"
else
    print_status ".env file exists, keeping current configuration"
fi

# Generate app key if not set
php artisan key:generate --force

print_status "Phase 4: Frontend Assets"

# Install Node.js dependencies
npm ci --production

# Build production assets
print_status "Building frontend assets with Vite..."
npm run build

# Verify build directory exists
if [ ! -d "public/build" ]; then
    print_error "Build directory not found! Asset compilation failed."
    exit 1
fi

print_status "Frontend assets built successfully"

print_status "Phase 5: Database & Cache"

# Run database migrations
php artisan migrate --force

# Create initial blog post if none exist
php artisan tinker --execute="
if (App\Models\BlogPost::count() === 0) {
    App\Models\BlogCategory::firstOrCreate([
        'name' => 'Development Journey',
        'slug' => 'development-journey'
    ], [
        'description' => 'Updates about our platform development and new features',
        'is_active' => true
    ]);
    
    App\Models\BlogPost::create([
        'user_id' => 1,
        'blog_category_id' => 1,
        'title' => 'Welcome to the Ableton Cookbook Blog!',
        'slug' => 'welcome-to-ableton-cookbook-blog',
        'excerpt' => 'We are excited to share our development journey, platform metrics, and insights with the Ableton Live community through this new blog.',
        'content' => 'Welcome to the official Ableton Cookbook blog! This is where we will share updates about our platform development, interesting statistics about rack sharing trends, and insights from the community. Stay tuned as we continue to build the best platform for sharing and discovering Ableton Live racks!',
        'published_at' => now(),
        'featured' => true,
        'is_active' => true
    ]);
    echo \"Blog post created successfully!\";
} else {
    echo \"Blog posts already exist\";
}
"

# Clear and optimize caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Cache for production performance
php artisan config:cache
php artisan view:cache
php artisan route:cache

print_status "Phase 6: File Permissions"

# Set proper permissions
chown -R www-data:www-data $APP_PATH
chmod -R 755 $APP_PATH
chmod -R 775 $APP_PATH/storage
chmod -R 775 $APP_PATH/bootstrap/cache
chmod -R 755 $APP_PATH/public

print_status "Phase 7: Services Restart"

# Restart services
systemctl reload nginx
systemctl restart php8.2-fpm

# Restart queue workers if supervisor is configured
if systemctl is-active --quiet supervisor; then
    supervisorctl reread
    supervisorctl update
    supervisorctl restart laravel-worker:*
    print_status "Queue workers restarted"
fi

print_status "Phase 8: Verification"

# Test the site
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://$DOMAIN)
if [ $HTTP_STATUS -eq 200 ]; then
    print_status "Site is responding correctly (HTTP $HTTP_STATUS)"
else
    print_warning "Site returned HTTP $HTTP_STATUS - please check manually"
fi

# Check if assets are loading
ASSET_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://$DOMAIN/build/assets/app-*.css)
if [ $ASSET_STATUS -eq 200 ]; then
    print_status "CSS assets are loading correctly"
else
    print_warning "CSS assets may not be loading (HTTP $ASSET_STATUS)"
fi

echo ""
print_status "🎉 Deployment Complete!"
echo -e "${GREEN}Site: https://$DOMAIN${NC}"
echo -e "${GREEN}Blog: https://$DOMAIN/blog${NC}"
echo -e "${GREEN}Admin: https://$DOMAIN/admin/blog${NC}"
echo ""
print_warning "Don't forget to:"
echo "1. Update .env with proper database credentials"
echo "2. Configure mail settings in .env"  
echo "3. Test all functionality manually"
echo ""
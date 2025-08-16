#!/bin/bash

# Shared Hosting Deployment Script for Ableton Cookbook
# This script prepares your Laravel app for deployment on shared hosting

set -e

echo "🚀 Starting shared hosting deployment preparation..."

cd laravel-app

# 0. Temporarily set production environment for build
if [ -f .env ]; then
    cp .env .env.backup
    sed -i.bak 's/APP_ENV=.*/APP_ENV=production/' .env
    sed -i.bak 's/APP_DEBUG=.*/APP_DEBUG=false/' .env
fi

# 1. Install dependencies (production only)
echo "📦 Installing production dependencies..."
COMPOSER_PROCESS_TIMEOUT=600 composer install --no-dev --optimize-autoloader --no-scripts
COMPOSER_PROCESS_TIMEOUT=600 composer dump-autoload --optimize --no-dev --no-scripts

# 2. Build frontend assets
echo "🎨 Building frontend assets..."
npm ci
npm run build

# 3. Generate optimized files (skip for shared hosting - will be done on server)
echo "⚡ Skipping optimization (will be done on server)..."
# Note: Artisan commands require full Laravel environment
# These will be run manually on the shared hosting server after upload

# 4. Create deployment archive
echo "📁 Creating deployment archive..."
cd ..
tar -czf ableton-cookbook-deploy.tar.gz \
    --exclude=laravel-app/node_modules \
    --exclude=laravel-app/.git \
    --exclude=laravel-app/tests \
    --exclude=laravel-app/phpunit.xml \
    --exclude=laravel-app/.env \
    --exclude=laravel-app/.env.local \
    --exclude=laravel-app/storage/app/private/* \
    --exclude=laravel-app/storage/logs/* \
    --exclude=laravel-app/storage/framework/cache/* \
    --exclude=laravel-app/storage/framework/sessions/* \
    --exclude=laravel-app/storage/framework/views/* \
    laravel-app/

# 5. Restore original .env if we backed it up
if [ -f laravel-app/.env.backup ]; then
    mv laravel-app/.env.backup laravel-app/.env
    rm -f laravel-app/.env.bak
fi

echo "✅ Deployment archive created: ableton-cookbook-deploy.tar.gz"
echo ""
echo "📋 Next steps:"
echo "1. Upload ableton-cookbook-deploy.tar.gz to your hosting"
echo "2. Extract it in your web root"
echo "3. Configure your .env file with production settings"
echo "4. Run database migrations: php artisan migrate"
echo "5. Run optimization commands:"
echo "   php artisan config:cache"
echo "   php artisan route:cache"
echo "   php artisan view:cache"
echo "   php artisan event:cache"
echo "6. Set proper file permissions"
echo ""
echo "See DEPLOY-SHARED-HOSTING.md for detailed instructions"
#!/bin/bash

# This script creates a self-contained deployment bundle
# that you can manually transfer to your VM

echo "📦 Creating deployment bundle for manual transfer..."

# Create a temporary directory for the bundle
BUNDLE_DIR="deployment-bundle-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BUNDLE_DIR"

# Create a tarball of the Laravel app
echo "Creating application archive..."
cd laravel-app
tar -czf "../$BUNDLE_DIR/app.tar.gz" \
    --exclude=node_modules \
    --exclude=.git \
    --exclude=storage/logs/* \
    --exclude=storage/framework/cache/* \
    --exclude=storage/framework/sessions/* \
    --exclude=storage/framework/views/* \
    .

cd ..

# Create a single deployment script
cat > "$BUNDLE_DIR/deploy.sh" << 'DEPLOY_SCRIPT'
#!/bin/bash

# One-command deployment script for Ableton Cookbook
# Run this on your VM after transferring the bundle

set -e

echo "🎵 Starting Ableton Cookbook deployment..."

# Extract the application
echo "Extracting application..."
rm -rf /var/www/ableton-cookbook
mkdir -p /var/www/ableton-cookbook
cd /var/www/ableton-cookbook
tar -xzf /tmp/app.tar.gz

# Create .env file
echo "Creating environment file..."
cat > .env << 'EOF'
APP_NAME="Ableton Cookbook"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://your-server-ip

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/ableton-cookbook/database/database.sqlite

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
EOF

# Setup database
mkdir -p database
touch database/database.sqlite
chmod 664 database/database.sqlite

# Generate key and migrate
php artisan key:generate
php artisan migrate --force

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
chown -R www-data:www-data /var/www/ableton-cookbook
chmod -R 755 /var/www/ableton-cookbook
chmod -R 775 storage bootstrap/cache database

# Restart services
systemctl restart php8.2-fpm nginx

echo "✅ Deployment complete!"
echo "Application available at http://$(hostname -I | awk '{print $1}')"
DEPLOY_SCRIPT

chmod +x "$BUNDLE_DIR/deploy.sh"

# Create transfer instructions
cat > "$BUNDLE_DIR/INSTRUCTIONS.txt" << 'EOF'
DEPLOYMENT INSTRUCTIONS
======================

1. Transfer this bundle to your VM:
   - Via web console file upload
   - Via copy-paste through terminal
   - Via temporary file sharing service

2. On your VM, run these commands:
   
   # Move files to /tmp
   mv app.tar.gz /tmp/
   
   # Run deployment
   sudo bash deploy.sh

3. That's it! Your app will be deployed.

If you need to transfer via copy-paste:
   # On local machine:
   base64 app.tar.gz > app.tar.gz.b64
   
   # Copy the content, then on VM:
   base64 -d > /tmp/app.tar.gz
   # Paste content, then Ctrl+D
EOF

echo ""
echo "✅ Bundle created in: $BUNDLE_DIR/"
echo ""
echo "Files in bundle:"
ls -lh "$BUNDLE_DIR/"
echo ""
echo "Next steps:"
echo "1. Transfer $BUNDLE_DIR/app.tar.gz to your VM's /tmp directory"
echo "2. Transfer $BUNDLE_DIR/deploy.sh to your VM"  
echo "3. Run: sudo bash deploy.sh"
echo ""
echo "Alternative: Use base64 encoding for copy-paste transfer:"
echo "   base64 $BUNDLE_DIR/app.tar.gz | pbcopy"
echo "   (This copies the encoded file to clipboard for pasting)"
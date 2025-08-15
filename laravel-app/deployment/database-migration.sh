#!/bin/bash

# Ableton Cookbook Database Migration Script
# SQLite to MySQL Production Migration with Data Preservation

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
SQLITE_DB="/var/www/ableton-cookbook/current/database/database.sqlite"
MYSQL_HOST="localhost"
MYSQL_DB="ableton_cookbook"
MYSQL_USER="ableton_user"
MYSQL_PASS="abletonCookbook2024!"
BACKUP_DIR="/var/www/ableton-cookbook/shared/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Create backup directory
create_backup_dir() {
    log_info "Creating backup directory..."
    mkdir -p "$BACKUP_DIR"
}

# Backup SQLite database
backup_sqlite() {
    log_info "Creating SQLite backup..."
    cp "$SQLITE_DB" "$BACKUP_DIR/database_backup_$TIMESTAMP.sqlite"
    log_info "SQLite backup created: $BACKUP_DIR/database_backup_$TIMESTAMP.sqlite"
}

# Backup MySQL database (if exists)
backup_mysql() {
    log_info "Creating MySQL backup..."
    mysqldump -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASS" "$MYSQL_DB" > "$BACKUP_DIR/mysql_backup_$TIMESTAMP.sql" 2>/dev/null || log_warn "No existing MySQL database to backup"
}

# Export SQLite data to SQL format
export_sqlite_data() {
    log_info "Exporting SQLite data to SQL format..."
    
    # Create SQLite dump with MySQL-compatible syntax
    sqlite3 "$SQLITE_DB" << 'EOF' > "$BACKUP_DIR/sqlite_export_$TIMESTAMP.sql"
.mode insert
.output stdout

-- Export users table
SELECT 'INSERT INTO users VALUES(' || 
    quote(id) || ',' ||
    quote(name) || ',' ||
    quote(email) || ',' ||
    quote(email_verified_at) || ',' ||
    quote(password) || ',' ||
    quote(two_factor_secret) || ',' ||
    quote(two_factor_recovery_codes) || ',' ||
    quote(two_factor_confirmed_at) || ',' ||
    quote(remember_token) || ',' ||
    quote(current_team_id) || ',' ||
    quote(profile_photo_path) || ',' ||
    quote(created_at) || ',' ||
    quote(updated_at) || ',' ||
    quote(bio) || ',' ||
    quote(location) || ',' ||
    quote(website) || ',' ||
    quote(social_links) || ',' ||
    quote(notification_preferences) || 
    ');' FROM users;

-- Export racks table
SELECT 'INSERT INTO racks VALUES(' || 
    quote(id) || ',' ||
    quote(uuid) || ',' ||
    quote(user_id) || ',' ||
    quote(title) || ',' ||
    quote(description) || ',' ||
    quote(slug) || ',' ||
    quote(file_path) || ',' ||
    quote(file_hash) || ',' ||
    quote(file_size) || ',' ||
    quote(original_filename) || ',' ||
    quote(rack_type) || ',' ||
    quote(category) || ',' ||
    quote(device_count) || ',' ||
    quote(chain_count) || ',' ||
    quote(ableton_version) || ',' ||
    quote(ableton_edition) || ',' ||
    quote(macro_controls) || ',' ||
    quote(devices) || ',' ||
    quote(chains) || ',' ||
    quote(chain_annotations) || ',' ||
    quote(version_details) || ',' ||
    quote(parsing_errors) || ',' ||
    quote(parsing_warnings) || ',' ||
    quote(preview_audio_path) || ',' ||
    quote(preview_image_path) || ',' ||
    quote(status) || ',' ||
    quote(processing_error) || ',' ||
    quote(published_at) || ',' ||
    quote(average_rating) || ',' ||
    quote(ratings_count) || ',' ||
    quote(downloads_count) || ',' ||
    quote(views_count) || ',' ||
    quote(comments_count) || ',' ||
    quote(likes_count) || ',' ||
    quote(is_public) || ',' ||
    quote(is_featured) || ',' ||
    quote(created_at) || ',' ||
    quote(updated_at) ||
    ');' FROM racks;

-- Export other tables
.tables
EOF
    
    log_info "SQLite data exported to: $BACKUP_DIR/sqlite_export_$TIMESTAMP.sql"
}

# Create comprehensive migration script
create_migration_script() {
    log_info "Creating comprehensive migration script..."
    
    cat << 'EOF' > "$BACKUP_DIR/migrate_to_mysql_$TIMESTAMP.sql"
-- Ableton Cookbook MySQL Migration Script
-- Generated automatically from SQLite data

SET FOREIGN_KEY_CHECKS = 0;
SET AUTOCOMMIT = 0;
START TRANSACTION;

-- Clear existing data
TRUNCATE TABLE users;
TRUNCATE TABLE racks;
TRUNCATE TABLE tags;
TRUNCATE TABLE rack_tags;
TRUNCATE TABLE rack_ratings;
TRUNCATE TABLE comments;
TRUNCATE TABLE rack_downloads;
TRUNCATE TABLE rack_favorites;
TRUNCATE TABLE collections;
TRUNCATE TABLE collection_racks;
TRUNCATE TABLE user_activity_feeds;
TRUNCATE TABLE rack_reports;
TRUNCATE TABLE notifications;

-- Reset auto-increment values
ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE racks AUTO_INCREMENT = 1;
ALTER TABLE tags AUTO_INCREMENT = 1;
ALTER TABLE rack_ratings AUTO_INCREMENT = 1;
ALTER TABLE comments AUTO_INCREMENT = 1;
ALTER TABLE rack_downloads AUTO_INCREMENT = 1;
ALTER TABLE rack_favorites AUTO_INCREMENT = 1;
ALTER TABLE collections AUTO_INCREMENT = 1;
ALTER TABLE user_activity_feeds AUTO_INCREMENT = 1;
ALTER TABLE rack_reports AUTO_INCREMENT = 1;
ALTER TABLE notifications AUTO_INCREMENT = 1;

EOF
    
    # Extract and convert SQLite data
    log_info "Converting SQLite data to MySQL format..."
    
    # Export users
    sqlite3 "$SQLITE_DB" "SELECT 
        'INSERT INTO users (id, name, email, email_verified_at, password, two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at, remember_token, current_team_id, profile_photo_path, created_at, updated_at, bio, location, website, social_links, notification_preferences) VALUES (' ||
        id || ', ' ||
        quote(name) || ', ' ||
        quote(email) || ', ' ||
        CASE WHEN email_verified_at IS NULL THEN 'NULL' ELSE quote(email_verified_at) END || ', ' ||
        quote(password) || ', ' ||
        CASE WHEN two_factor_secret IS NULL THEN 'NULL' ELSE quote(two_factor_secret) END || ', ' ||
        CASE WHEN two_factor_recovery_codes IS NULL THEN 'NULL' ELSE quote(two_factor_recovery_codes) END || ', ' ||
        CASE WHEN two_factor_confirmed_at IS NULL THEN 'NULL' ELSE quote(two_factor_confirmed_at) END || ', ' ||
        CASE WHEN remember_token IS NULL THEN 'NULL' ELSE quote(remember_token) END || ', ' ||
        CASE WHEN current_team_id IS NULL THEN 'NULL' ELSE current_team_id END || ', ' ||
        CASE WHEN profile_photo_path IS NULL THEN 'NULL' ELSE quote(profile_photo_path) END || ', ' ||
        quote(created_at) || ', ' ||
        quote(updated_at) || ', ' ||
        CASE WHEN bio IS NULL THEN 'NULL' ELSE quote(bio) END || ', ' ||
        CASE WHEN location IS NULL THEN 'NULL' ELSE quote(location) END || ', ' ||
        CASE WHEN website IS NULL THEN 'NULL' ELSE quote(website) END || ', ' ||
        CASE WHEN social_links IS NULL THEN 'NULL' ELSE quote(social_links) END || ', ' ||
        CASE WHEN notification_preferences IS NULL THEN 'NULL' ELSE quote(notification_preferences) END ||
        ');'
    FROM users;" >> "$BACKUP_DIR/migrate_to_mysql_$TIMESTAMP.sql"
    
    # Export racks
    sqlite3 "$SQLITE_DB" "SELECT 
        'INSERT INTO racks (id, uuid, user_id, title, description, slug, file_path, file_hash, file_size, original_filename, rack_type, category, device_count, chain_count, ableton_version, ableton_edition, macro_controls, devices, chains, chain_annotations, version_details, parsing_errors, parsing_warnings, preview_audio_path, preview_image_path, status, processing_error, published_at, average_rating, ratings_count, downloads_count, views_count, comments_count, likes_count, is_public, is_featured, created_at, updated_at) VALUES (' ||
        id || ', ' ||
        quote(uuid) || ', ' ||
        user_id || ', ' ||
        quote(title) || ', ' ||
        quote(description) || ', ' ||
        quote(slug) || ', ' ||
        quote(file_path) || ', ' ||
        quote(file_hash) || ', ' ||
        file_size || ', ' ||
        quote(original_filename) || ', ' ||
        CASE WHEN rack_type IS NULL THEN 'NULL' ELSE quote(rack_type) END || ', ' ||
        CASE WHEN category IS NULL THEN 'NULL' ELSE quote(category) END || ', ' ||
        CASE WHEN device_count IS NULL THEN 'NULL' ELSE device_count END || ', ' ||
        CASE WHEN chain_count IS NULL THEN 'NULL' ELSE chain_count END || ', ' ||
        CASE WHEN ableton_version IS NULL THEN 'NULL' ELSE quote(ableton_version) END || ', ' ||
        CASE WHEN ableton_edition IS NULL THEN 'NULL' ELSE quote(ableton_edition) END || ', ' ||
        CASE WHEN macro_controls IS NULL THEN 'NULL' ELSE quote(macro_controls) END || ', ' ||
        CASE WHEN devices IS NULL THEN 'NULL' ELSE quote(devices) END || ', ' ||
        CASE WHEN chains IS NULL THEN 'NULL' ELSE quote(chains) END || ', ' ||
        CASE WHEN chain_annotations IS NULL THEN 'NULL' ELSE quote(chain_annotations) END || ', ' ||
        CASE WHEN version_details IS NULL THEN 'NULL' ELSE quote(version_details) END || ', ' ||
        CASE WHEN parsing_errors IS NULL THEN 'NULL' ELSE quote(parsing_errors) END || ', ' ||
        CASE WHEN parsing_warnings IS NULL THEN 'NULL' ELSE quote(parsing_warnings) END || ', ' ||
        CASE WHEN preview_audio_path IS NULL THEN 'NULL' ELSE quote(preview_audio_path) END || ', ' ||
        CASE WHEN preview_image_path IS NULL THEN 'NULL' ELSE quote(preview_image_path) END || ', ' ||
        CASE WHEN status IS NULL THEN '''pending''' ELSE quote(status) END || ', ' ||
        CASE WHEN processing_error IS NULL THEN 'NULL' ELSE quote(processing_error) END || ', ' ||
        CASE WHEN published_at IS NULL THEN 'NULL' ELSE quote(published_at) END || ', ' ||
        CASE WHEN average_rating IS NULL THEN '0.00' ELSE average_rating END || ', ' ||
        CASE WHEN ratings_count IS NULL THEN '0' ELSE ratings_count END || ', ' ||
        CASE WHEN downloads_count IS NULL THEN '0' ELSE downloads_count END || ', ' ||
        CASE WHEN views_count IS NULL THEN '0' ELSE views_count END || ', ' ||
        CASE WHEN comments_count IS NULL THEN '0' ELSE comments_count END || ', ' ||
        CASE WHEN likes_count IS NULL THEN '0' ELSE likes_count END || ', ' ||
        CASE WHEN is_public = 1 THEN '1' ELSE '0' END || ', ' ||
        CASE WHEN is_featured = 1 THEN '1' ELSE '0' END || ', ' ||
        quote(created_at) || ', ' ||
        quote(updated_at) ||
        ');'
    FROM racks;" >> "$BACKUP_DIR/migrate_to_mysql_$TIMESTAMP.sql"
    
    # Export other tables if they exist
    for table in tags rack_tags rack_ratings comments rack_downloads rack_favorites collections collection_racks user_activity_feeds rack_reports notifications; do
        if sqlite3 "$SQLITE_DB" ".tables" | grep -q "$table"; then
            log_info "Exporting $table table..."
            sqlite3 "$SQLITE_DB" ".mode insert $table" ".output stdout" "SELECT * FROM $table;" >> "$BACKUP_DIR/migrate_to_mysql_$TIMESTAMP.sql" || log_warn "Failed to export $table"
        fi
    done
    
    # Add transaction completion
    cat << 'EOF' >> "$BACKUP_DIR/migrate_to_mysql_$TIMESTAMP.sql"

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
SET AUTOCOMMIT = 1;

-- Update auto-increment values to max ID + 1
SET @max_user_id = (SELECT COALESCE(MAX(id), 0) FROM users);
SET @sql = CONCAT('ALTER TABLE users AUTO_INCREMENT = ', @max_user_id + 1);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @max_rack_id = (SELECT COALESCE(MAX(id), 0) FROM racks);
SET @sql = CONCAT('ALTER TABLE racks AUTO_INCREMENT = ', @max_rack_id + 1);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify data integrity
SELECT 'Data verification:' as status;
SELECT 'Users count:' as table_name, COUNT(*) as count FROM users;
SELECT 'Racks count:' as table_name, COUNT(*) as count FROM racks;
SELECT 'Tags count:' as table_name, COUNT(*) as count FROM tags;
SELECT 'Ratings count:' as table_name, COUNT(*) as count FROM rack_ratings;
SELECT 'Comments count:' as table_name, COUNT(*) as count FROM comments;
SELECT 'Downloads count:' as table_name, COUNT(*) as count FROM rack_downloads;
EOF
    
    log_info "Migration script created: $BACKUP_DIR/migrate_to_mysql_$TIMESTAMP.sql"
}

# Execute MySQL migration
execute_migration() {
    log_info "Executing MySQL migration..."
    
    # Test MySQL connection
    mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASS" -e "SELECT 1" > /dev/null
    if [ $? -ne 0 ]; then
        log_error "Cannot connect to MySQL database"
        exit 1
    fi
    
    # Execute migration
    mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASS" "$MYSQL_DB" < "$BACKUP_DIR/migrate_to_mysql_$TIMESTAMP.sql"
    
    if [ $? -eq 0 ]; then
        log_info "Migration completed successfully!"
    else
        log_error "Migration failed. Check the error messages above."
        exit 1
    fi
}

# Update Laravel configuration
update_laravel_config() {
    log_info "Updating Laravel configuration for MySQL..."
    
    # Create production .env file
    cat << EOF > "/var/www/ableton-cookbook/shared/.env"
APP_NAME="Ableton Cookbook"
APP_ENV=production
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=https://ableton.recipes

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

PHP_CLI_SERVER_WORKERS=4

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single,stderr
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$MYSQL_DB
DB_USERNAME=$MYSQL_USER
DB_PASSWORD=$MYSQL_PASS

# Session Configuration
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.ableton.recipes

# Broadcasting
BROADCAST_CONNECTION=log

# Filesystem
FILESYSTEM_DISK=local

# Queue Configuration
QUEUE_CONNECTION=redis

# Cache Configuration
CACHE_STORE=redis

# Redis Configuration
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="noreply@ableton.recipes"
MAIL_FROM_NAME="\${APP_NAME}"

# AWS Configuration (for future S3 setup)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

# Application-specific
VITE_APP_NAME="\${APP_NAME}"

# Security
SANCTUM_STATEFUL_DOMAINS=ableton.recipes,www.ableton.recipes
SESSION_SECURE_COOKIE=true
EOF
    
    log_info "Laravel configuration updated for production MySQL environment"
}

# Verify migration integrity
verify_migration() {
    log_info "Verifying migration integrity..."
    
    # Count records in SQLite
    SQLITE_USERS=$(sqlite3 "$SQLITE_DB" "SELECT COUNT(*) FROM users;")
    SQLITE_RACKS=$(sqlite3 "$SQLITE_DB" "SELECT COUNT(*) FROM racks;")
    
    # Count records in MySQL
    MYSQL_USERS=$(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASS" -N -e "SELECT COUNT(*) FROM users;" "$MYSQL_DB")
    MYSQL_RACKS=$(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASS" -N -e "SELECT COUNT(*) FROM racks;" "$MYSQL_DB")
    
    log_info "Data verification results:"
    echo "  SQLite Users: $SQLITE_USERS -> MySQL Users: $MYSQL_USERS"
    echo "  SQLite Racks: $SQLITE_RACKS -> MySQL Racks: $MYSQL_RACKS"
    
    if [ "$SQLITE_USERS" -eq "$MYSQL_USERS" ] && [ "$SQLITE_RACKS" -eq "$MYSQL_RACKS" ]; then
        log_info "✅ Data migration verification successful!"
    else
        log_warn "⚠️  Data counts don't match. Please review the migration manually."
    fi
}

# Create rollback script
create_rollback_script() {
    log_info "Creating rollback script..."
    
    cat << EOF > "$BACKUP_DIR/rollback_to_sqlite_$TIMESTAMP.sh"
#!/bin/bash

# Rollback script for Ableton Cookbook
# Restores SQLite database and reverts configuration

set -e

log_info() {
    echo -e "\033[0;32m[ROLLBACK]\033[0m \$1"
}

log_info "Rolling back to SQLite database..."

# Restore SQLite database
cp "$BACKUP_DIR/database_backup_$TIMESTAMP.sqlite" "/var/www/ableton-cookbook/current/database/database.sqlite"

# Restore SQLite configuration
cat << 'ENVEOF' > "/var/www/ableton-cookbook/shared/.env"
APP_NAME="Ableton Cookbook"
APP_ENV=production
APP_KEY=base64:\$(openssl rand -base64 32)
APP_DEBUG=false
APP_URL=https://ableton.recipes

DB_CONNECTION=sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=120

CACHE_STORE=database
QUEUE_CONNECTION=database

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@ableton.recipes"
MAIL_FROM_NAME="\${APP_NAME}"

VITE_APP_NAME="\${APP_NAME}"
ENVEOF

log_info "Rollback completed successfully!"
log_info "Please restart PHP-FPM and clear Laravel caches."
EOF
    
    chmod +x "$BACKUP_DIR/rollback_to_sqlite_$TIMESTAMP.sh"
    log_info "Rollback script created: $BACKUP_DIR/rollback_to_sqlite_$TIMESTAMP.sh"
}

# Main execution
main() {
    log_info "🗄️  Starting Ableton Cookbook database migration..."
    
    if [ ! -f "$SQLITE_DB" ]; then
        log_error "SQLite database not found at: $SQLITE_DB"
        exit 1
    fi
    
    create_backup_dir
    backup_sqlite
    backup_mysql
    create_migration_script
    execute_migration
    update_laravel_config
    verify_migration
    create_rollback_script
    
    log_info "🎉 Database migration completed successfully!"
    log_warn "Important next steps:"
    echo "1. Test the application thoroughly"
    echo "2. Run: php artisan config:cache"
    echo "3. Run: php artisan migrate --force (to ensure all migrations are applied)"
    echo "4. Restart queue workers: sudo supervisorctl restart ableton-cookbook:*"
    echo "5. Monitor application logs for any issues"
    echo ""
    echo "Backup files location: $BACKUP_DIR"
    echo "Rollback script: $BACKUP_DIR/rollback_to_sqlite_$TIMESTAMP.sh"
}

# Run main function
main "$@"
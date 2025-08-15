#!/bin/bash

# Ableton Cookbook File Upload Security Configuration
# Secure handling of .adg files with comprehensive validation and storage

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
STORAGE_DIR="$SHARED_DIR/storage"
UPLOAD_DIR="$STORAGE_DIR/app/private/racks"
QUARANTINE_DIR="$STORAGE_DIR/app/quarantine"
LOG_DIR="/var/log/ableton-cookbook"

# Create secure file upload structure
create_upload_structure() {
    log_info "Creating secure file upload structure..."
    
    # Create directories with proper permissions
    sudo -u deploy mkdir -p "$UPLOAD_DIR"
    sudo -u deploy mkdir -p "$QUARANTINE_DIR"
    sudo -u deploy mkdir -p "$LOG_DIR"
    sudo -u deploy mkdir -p "$STORAGE_DIR/app/temp"
    sudo -u deploy mkdir -p "$STORAGE_DIR/app/scanned"
    
    # Set restrictive permissions
    chmod 750 "$UPLOAD_DIR"
    chmod 700 "$QUARANTINE_DIR"
    chmod 755 "$LOG_DIR"
    chmod 700 "$STORAGE_DIR/app/temp"
    
    # Ensure www-data can read but not write
    chown -R deploy:www-data "$STORAGE_DIR/app"
    
    log_info "Upload structure created successfully"
}

# Install ClamAV for malware scanning
install_clamav() {
    log_info "Installing ClamAV antivirus scanner..."
    
    sudo apt update
    sudo apt install -y clamav clamav-daemon clamav-freshclam
    
    # Update virus definitions
    sudo freshclam
    
    # Start and enable ClamAV daemon
    sudo systemctl start clamav-daemon
    sudo systemctl enable clamav-daemon
    
    # Configure automatic updates
    sudo systemctl start clamav-freshclam
    sudo systemctl enable clamav-freshclam
    
    log_info "ClamAV installed and configured"
}

# Create file validation script
create_file_validator() {
    log_info "Creating file validation script..."
    
    cat << 'EOF' > "$SHARED_DIR/scripts/validate-adg-file.sh"
#!/bin/bash

# Ableton .adg File Validation Script
# Comprehensive security validation for uploaded rack files

set -e

FILE_PATH="$1"
QUARANTINE_DIR="/var/www/ableton-cookbook/shared/storage/app/quarantine"
LOG_FILE="/var/log/ableton-cookbook/file-validation.log"

# Logging function
log_validation() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# Validate file existence
if [ ! -f "$FILE_PATH" ]; then
    log_validation "ERROR: File not found: $FILE_PATH"
    exit 1
fi

# Get file info
FILE_SIZE=$(stat -f%z "$FILE_PATH" 2>/dev/null || stat -c%s "$FILE_PATH" 2>/dev/null)
FILE_MIME=$(file -b --mime-type "$FILE_PATH")
FILE_NAME=$(basename "$FILE_PATH")

log_validation "INFO: Validating file: $FILE_NAME (Size: $FILE_SIZE bytes, MIME: $FILE_MIME)"

# Size validation (max 25MB)
MAX_SIZE=26214400
if [ "$FILE_SIZE" -gt "$MAX_SIZE" ]; then
    log_validation "ERROR: File too large: $FILE_SIZE bytes (max: $MAX_SIZE)"
    mv "$FILE_PATH" "$QUARANTINE_DIR/"
    exit 1
fi

# MIME type validation
if [ "$FILE_MIME" != "application/octet-stream" ] && [ "$FILE_MIME" != "application/x-gzip" ]; then
    log_validation "ERROR: Invalid MIME type: $FILE_MIME"
    mv "$FILE_PATH" "$QUARANTINE_DIR/"
    exit 1
fi

# File extension validation
if [[ ! "$FILE_NAME" =~ \.adg$ ]]; then
    log_validation "ERROR: Invalid file extension: $FILE_NAME"
    mv "$FILE_PATH" "$QUARANTINE_DIR/"
    exit 1
fi

# Magic number validation (gzip header)
MAGIC_BYTES=$(xxd -l 3 -p "$FILE_PATH")
if [ "$MAGIC_BYTES" != "1f8b08" ]; then
    log_validation "ERROR: Invalid file format (not gzipped): $MAGIC_BYTES"
    mv "$FILE_PATH" "$QUARANTINE_DIR/"
    exit 1
fi

# Virus scan with ClamAV
log_validation "INFO: Starting virus scan for: $FILE_NAME"
SCAN_RESULT=$(clamscan --no-summary "$FILE_PATH" 2>&1)
SCAN_EXIT_CODE=$?

if [ $SCAN_EXIT_CODE -ne 0 ]; then
    log_validation "ERROR: Virus detected or scan failed: $SCAN_RESULT"
    mv "$FILE_PATH" "$QUARANTINE_DIR/"
    exit 1
fi

# Validate decompressed content
log_validation "INFO: Validating decompressed content for: $FILE_NAME"
TEMP_DIR=$(mktemp -d)
gunzip -c "$FILE_PATH" > "$TEMP_DIR/content.xml" 2>/dev/null || {
    log_validation "ERROR: Failed to decompress file"
    rm -rf "$TEMP_DIR"
    mv "$FILE_PATH" "$QUARANTINE_DIR/"
    exit 1
}

# Check if decompressed content is valid XML
xmllint --noout "$TEMP_DIR/content.xml" 2>/dev/null || {
    log_validation "ERROR: Invalid XML content"
    rm -rf "$TEMP_DIR"
    mv "$FILE_PATH" "$QUARANTINE_DIR/"
    exit 1
}

# Check for Ableton-specific XML structure
if ! grep -q "Ableton Live Set" "$TEMP_DIR/content.xml" && ! grep -q "DeviceChain" "$TEMP_DIR/content.xml"; then
    log_validation "ERROR: Not a valid Ableton rack file"
    rm -rf "$TEMP_DIR"
    mv "$FILE_PATH" "$QUARANTINE_DIR/"
    exit 1
fi

# Clean up temp directory
rm -rf "$TEMP_DIR"

log_validation "SUCCESS: File validation passed for: $FILE_NAME"
exit 0
EOF
    
    chmod +x "$SHARED_DIR/scripts/validate-adg-file.sh"
    chown deploy:deploy "$SHARED_DIR/scripts/validate-adg-file.sh"
    
    log_info "File validation script created"
}

# Create secure file processor
create_file_processor() {
    log_info "Creating secure file processor..."
    
    cat << 'EOF' > "$SHARED_DIR/scripts/process-rack-file.php"
<?php

/**
 * Secure Ableton Rack File Processor
 * Handles .adg file processing with security validations
 */

class SecureRackProcessor
{
    private $uploadDir;
    private $quarantineDir;
    private $tempDir;
    private $logFile;
    
    public function __construct()
    {
        $this->uploadDir = '/var/www/ableton-cookbook/shared/storage/app/private/racks';
        $this->quarantineDir = '/var/www/ableton-cookbook/shared/storage/app/quarantine';
        $this->tempDir = '/var/www/ableton-cookbook/shared/storage/app/temp';
        $this->logFile = '/var/log/ableton-cookbook/file-processing.log';
    }
    
    public function processFile($tempFilePath, $originalName)
    {
        $this->log("INFO: Starting processing for: {$originalName}");
        
        try {
            // Generate secure filename
            $hash = hash_file('sha256', $tempFilePath);
            $uuid = $this->generateUUID();
            $secureFilename = $uuid . '.adg';
            $finalPath = $this->uploadDir . '/' . $secureFilename;
            
            // Validate file
            $this->validateFile($tempFilePath, $originalName);
            
            // Move to final location
            if (!move_uploaded_file($tempFilePath, $finalPath)) {
                throw new Exception("Failed to move file to final location");
            }
            
            // Set secure permissions
            chmod($finalPath, 0644);
            chown($finalPath, 'deploy');
            chgrp($finalPath, 'www-data');
            
            $this->log("SUCCESS: File processed successfully: {$secureFilename}");
            
            return [
                'success' => true,
                'filename' => $secureFilename,
                'path' => 'racks/' . $secureFilename,
                'hash' => $hash,
                'size' => filesize($finalPath)
            ];
            
        } catch (Exception $e) {
            $this->log("ERROR: Processing failed for {$originalName}: " . $e->getMessage());
            
            // Move to quarantine if still exists
            if (file_exists($tempFilePath)) {
                $quarantinePath = $this->quarantineDir . '/' . time() . '_' . $originalName;
                move_uploaded_file($tempFilePath, $quarantinePath);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function validateFile($filePath, $originalName)
    {
        // File size validation
        $fileSize = filesize($filePath);
        if ($fileSize > 26214400) { // 25MB
            throw new Exception("File too large: {$fileSize} bytes");
        }
        
        // Extension validation
        if (!preg_match('/\.adg$/i', $originalName)) {
            throw new Exception("Invalid file extension");
        }
        
        // MIME type validation
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        if (!in_array($mimeType, ['application/octet-stream', 'application/x-gzip', 'application/gzip'])) {
            throw new Exception("Invalid MIME type: {$mimeType}");
        }
        
        // Magic number validation
        $handle = fopen($filePath, 'rb');
        $magicBytes = fread($handle, 3);
        fclose($handle);
        
        if (bin2hex($magicBytes) !== '1f8b08') {
            throw new Exception("Invalid file format (not gzipped)");
        }
        
        // Virus scan
        $this->scanForViruses($filePath);
        
        // Content validation
        $this->validateContent($filePath);
    }
    
    private function scanForViruses($filePath)
    {
        $output = [];
        $returnCode = 0;
        
        exec("clamscan --no-summary " . escapeshellarg($filePath) . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Virus scan failed or threat detected");
        }
    }
    
    private function validateContent($filePath)
    {
        // Create temporary file for decompressed content
        $tempFile = tempnam($this->tempDir, 'rack_validation_');
        
        try {
            // Decompress file
            $compressed = file_get_contents($filePath);
            $decompressed = gzdecode($compressed);
            
            if ($decompressed === false) {
                throw new Exception("Failed to decompress file");
            }
            
            file_put_contents($tempFile, $decompressed);
            
            // Validate XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_file($tempFile);
            
            if ($xml === false) {
                throw new Exception("Invalid XML content");
            }
            
            // Check for Ableton-specific elements
            $xmlString = file_get_contents($tempFile);
            if (strpos($xmlString, 'DeviceChain') === false && 
                strpos($xmlString, 'Ableton Live Set') === false) {
                throw new Exception("Not a valid Ableton rack file");
            }
            
        } finally {
            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
    
    private function generateUUID()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
    }
}

// CLI usage
if (php_sapi_name() === 'cli' && isset($argv[1]) && isset($argv[2])) {
    $processor = new SecureRackProcessor();
    $result = $processor->processFile($argv[1], $argv[2]);
    
    if ($result['success']) {
        echo json_encode($result);
        exit(0);
    } else {
        echo json_encode($result);
        exit(1);
    }
}
EOF
    
    chmod +x "$SHARED_DIR/scripts/process-rack-file.php"
    chown deploy:deploy "$SHARED_DIR/scripts/process-rack-file.php"
    
    log_info "Secure file processor created"
}

# Configure Nginx for secure file serving
configure_nginx_security() {
    log_info "Configuring Nginx security for file uploads..."
    
    # Create additional security configuration
    sudo tee /etc/nginx/conf.d/file-security.conf << 'EOF'
# File Upload Security Configuration
client_body_buffer_size 1M;
client_max_body_size 25M;
client_body_timeout 60s;
client_header_timeout 60s;

# Prevent access to sensitive file types
location ~* \.(sql|log|conf|ini|sh|txt|bak|old|tmp|temp|backup)$ {
    deny all;
    access_log off;
    log_not_found off;
}

# Prevent execution of uploaded files
location ~* ^/storage/.*\.(php|phtml|pl|py|jsp|asp|sh|cgi)$ {
    deny all;
    access_log off;
    log_not_found off;
}

# Secure headers for file downloads
location ~* \.(adg|zip|tar|gz)$ {
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    add_header Content-Security-Policy "default-src 'none'";
}

# Rate limiting for uploads
limit_req_zone $binary_remote_addr zone=upload:10m rate=5r/m;

location /upload {
    limit_req zone=upload burst=3 nodelay;
    
    # Additional upload security
    if ($request_method !~ ^(GET|POST)$) {
        return 405;
    }
    
    # Block requests with suspicious patterns
    if ($request_uri ~* "(\.\.\/|\.\.\\|%2e%2e|%252e%252e)") {
        return 403;
    }
}
EOF
    
    # Test nginx configuration
    sudo nginx -t
    
    log_info "Nginx security configuration updated"
}

# Create file cleanup script
create_cleanup_script() {
    log_info "Creating file cleanup script..."
    
    cat << 'EOF' > "$SHARED_DIR/scripts/cleanup-files.sh"
#!/bin/bash

# Ableton Cookbook File Cleanup Script
# Removes old temporary files and quarantined items

set -e

TEMP_DIR="/var/www/ableton-cookbook/shared/storage/app/temp"
QUARANTINE_DIR="/var/www/ableton-cookbook/shared/storage/app/quarantine"
LOG_FILE="/var/log/ableton-cookbook/cleanup.log"

log_cleanup() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

log_cleanup "INFO: Starting file cleanup process"

# Remove temporary files older than 1 hour
find "$TEMP_DIR" -type f -mmin +60 -delete 2>/dev/null || true
TEMP_CLEANED=$(find "$TEMP_DIR" -type f -mmin +60 2>/dev/null | wc -l)
log_cleanup "INFO: Cleaned $TEMP_CLEANED temporary files"

# Remove quarantined files older than 7 days
find "$QUARANTINE_DIR" -type f -mtime +7 -delete 2>/dev/null || true
QUARANTINE_CLEANED=$(find "$QUARANTINE_DIR" -type f -mtime +7 2>/dev/null | wc -l)
log_cleanup "INFO: Cleaned $QUARANTINE_CLEANED quarantined files"

# Remove empty directories
find "$TEMP_DIR" -type d -empty -delete 2>/dev/null || true
find "$QUARANTINE_DIR" -type d -empty -delete 2>/dev/null || true

log_cleanup "INFO: File cleanup completed"
EOF
    
    chmod +x "$SHARED_DIR/scripts/cleanup-files.sh"
    chown deploy:deploy "$SHARED_DIR/scripts/cleanup-files.sh"
    
    # Add to crontab
    (crontab -u deploy -l 2>/dev/null || true; echo "0 */6 * * * $SHARED_DIR/scripts/cleanup-files.sh") | crontab -u deploy -
    
    log_info "File cleanup script created and scheduled"
}

# Configure file upload monitoring
setup_monitoring() {
    log_info "Setting up file upload monitoring..."
    
    # Create monitoring script
    cat << 'EOF' > "$SHARED_DIR/scripts/monitor-uploads.sh"
#!/bin/bash

# Ableton Cookbook Upload Monitoring
# Monitors file upload patterns and security events

LOG_FILE="/var/log/ableton-cookbook/upload-monitor.log"
ALERT_THRESHOLD=10 # Alert if more than 10 files uploaded per hour by same IP

log_monitor() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# Check recent upload patterns
RECENT_UPLOADS=$(grep "$(date '+%Y-%m-%d %H')" /var/log/nginx/access.log | grep "POST /upload" | wc -l)

if [ "$RECENT_UPLOADS" -gt "$ALERT_THRESHOLD" ]; then
    log_monitor "ALERT: High upload volume detected: $RECENT_UPLOADS uploads in current hour"
    
    # Get top uploading IPs
    TOP_IPS=$(grep "$(date '+%Y-%m-%d %H')" /var/log/nginx/access.log | grep "POST /upload" | awk '{print $1}' | sort | uniq -c | sort -nr | head -5)
    log_monitor "Top uploading IPs: $TOP_IPS"
fi

# Check quarantine directory size
QUARANTINE_SIZE=$(du -sh "$QUARANTINE_DIR" 2>/dev/null | awk '{print $1}' || echo "0")
log_monitor "INFO: Current quarantine size: $QUARANTINE_SIZE"

# Check for suspicious file patterns
SUSPICIOUS_FILES=$(find "$QUARANTINE_DIR" -name "*.php" -o -name "*.exe" -o -name "*.scr" 2>/dev/null | wc -l)
if [ "$SUSPICIOUS_FILES" -gt 0 ]; then
    log_monitor "ALERT: $SUSPICIOUS_FILES suspicious files found in quarantine"
fi
EOF
    
    chmod +x "$SHARED_DIR/scripts/monitor-uploads.sh"
    chown deploy:deploy "$SHARED_DIR/scripts/monitor-uploads.sh"
    
    # Add to crontab (run every hour)
    (crontab -u deploy -l 2>/dev/null || true; echo "0 * * * * $SHARED_DIR/scripts/monitor-uploads.sh") | crontab -u deploy -
    
    log_info "Upload monitoring configured"
}

# Create custom Laravel validation rule
create_laravel_validation() {
    log_info "Creating Laravel custom validation rule..."
    
    mkdir -p "$SHARED_DIR/laravel-validation"
    
    cat << 'EOF' > "$SHARED_DIR/laravel-validation/AdgFileRule.php"
<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Log;

class AdgFileRule implements Rule
{
    private $errors = [];

    public function passes($attribute, $value)
    {
        if (!$value || !$value->isValid()) {
            $this->errors[] = 'Invalid file upload';
            return false;
        }

        // File size validation (25MB max)
        if ($value->getSize() > 26214400) {
            $this->errors[] = 'File size exceeds 25MB limit';
            return false;
        }

        // Extension validation
        if (strtolower($value->getClientOriginalExtension()) !== 'adg') {
            $this->errors[] = 'File must have .adg extension';
            return false;
        }

        // MIME type validation
        $mimeType = $value->getMimeType();
        if (!in_array($mimeType, ['application/octet-stream', 'application/x-gzip', 'application/gzip'])) {
            $this->errors[] = 'Invalid file type. Must be a compressed Ableton rack file.';
            return false;
        }

        // Magic number validation
        $handle = fopen($value->getPathname(), 'rb');
        $magicBytes = fread($handle, 3);
        fclose($handle);

        if (bin2hex($magicBytes) !== '1f8b08') {
            $this->errors[] = 'Invalid file format. File is not properly compressed.';
            return false;
        }

        // Content validation
        try {
            $compressed = file_get_contents($value->getPathname());
            $decompressed = gzdecode($compressed);

            if ($decompressed === false) {
                $this->errors[] = 'Unable to decompress file content';
                return false;
            }

            // Basic XML validation
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($decompressed);
            
            if ($xml === false) {
                $this->errors[] = 'File does not contain valid XML';
                return false;
            }

            // Check for Ableton-specific content
            if (strpos($decompressed, 'DeviceChain') === false && 
                strpos($decompressed, 'Ableton Live Set') === false) {
                $this->errors[] = 'File does not appear to be a valid Ableton rack';
                return false;
            }

        } catch (\Exception $e) {
            Log::error('ADG file validation error', [
                'file' => $value->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
            $this->errors[] = 'Unable to validate file content';
            return false;
        }

        return true;
    }

    public function message()
    {
        return implode(' ', $this->errors) ?: 'The uploaded file is not a valid Ableton rack file.';
    }
}
EOF
    
    log_info "Laravel validation rule created"
}

# Main execution
main() {
    log_info "🔒 Setting up file upload security for Ableton Cookbook..."
    
    # Create scripts directory
    sudo -u deploy mkdir -p "$SHARED_DIR/scripts"
    
    create_upload_structure
    install_clamav
    create_file_validator
    create_file_processor
    configure_nginx_security
    create_cleanup_script
    setup_monitoring
    create_laravel_validation
    
    # Reload Nginx
    sudo systemctl reload nginx
    
    log_info "🎉 File upload security setup completed successfully!"
    log_warn "Important security notes:"
    echo "1. All uploaded files are validated for size, type, and content"
    echo "2. Files are scanned for viruses using ClamAV"
    echo "3. Suspicious files are quarantined automatically"
    echo "4. Upload monitoring alerts for unusual patterns"
    echo "5. Automatic cleanup of temporary and old quarantined files"
    echo ""
    echo "Key security features:"
    echo "  ✓ File size limits (25MB maximum)"
    echo "  ✓ Extension and MIME type validation"
    echo "  ✓ Magic number verification"
    echo "  ✓ XML content validation"
    echo "  ✓ Virus scanning with ClamAV"
    echo "  ✓ Secure file storage with proper permissions"
    echo "  ✓ Rate limiting for uploads"
    echo "  ✓ Automated monitoring and cleanup"
    echo ""
    echo "Log files:"
    echo "  Upload validation: /var/log/ableton-cookbook/file-validation.log"
    echo "  File processing: /var/log/ableton-cookbook/file-processing.log"
    echo "  Upload monitoring: /var/log/ableton-cookbook/upload-monitor.log"
    echo "  Cleanup activity: /var/log/ableton-cookbook/cleanup.log"
}

# Run main function
main "$@"
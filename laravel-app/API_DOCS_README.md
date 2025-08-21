# API Documentation Configuration

This document explains how the API documentation server URLs are configured to work properly in both local and production environments.

## Problem

The L5-Swagger package for Laravel has limitations with dynamic server URL configuration. By default, it generates API documentation with hardcoded server URLs, which causes issues when deploying to different environments.

## Solution

We've implemented a two-part solution:

### 1. Base Configuration

The OpenAPI server configuration is defined in `app/Http/Controllers/Controller.php`:

```php
/**
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 */
```

The constant `L5_SWAGGER_CONST_HOST` is configured in `config/l5-swagger.php`:

```php
'constants' => [
    'L5_SWAGGER_CONST_HOST' => env('APP_URL', 'http://localhost:8000'),
],
```

### 2. Post-Processing Script

Due to limitations with constant replacement in L5-Swagger, we use a post-processing script (`fix-api-docs.php`) that:

1. Reads the generated API documentation JSON files
2. Updates the server URLs based on the current environment
3. Configures appropriate servers for each environment

#### Environment-Specific Behavior

**Local Development:**
- Shows both local development server and production server
- Allows developers to test against both environments

**Production:**
- Shows only the production server URL
- Uses the `APP_URL` environment variable

## Usage

### Manual Generation

```bash
# Generate base documentation
php artisan l5-swagger:generate

# Fix server URLs for current environment
php fix-api-docs.php
```

### Automatic Deployment

The production deployment script (`deployment/production-deploy.sh`) automatically:

1. Generates the API documentation
2. Fixes the server URLs
3. Verifies the documentation is accessible

## Files Modified

- `config/l5-swagger.php` - Updated constants configuration
- `app/Http/Controllers/Controller.php` - Added proper OpenAPI server annotation
- `fix-api-docs.php` - Post-processing script for server URL fixes
- `deployment/production-deploy.sh` - Added API docs generation to deployment
- `.env.example` - Added guidance for production APP_URL configuration

## Testing

The API documentation should be accessible at:
- Local: `http://localhost:8000/api/docs`
- Production: `https://your-domain.com/api/docs`

## Troubleshooting

If the API documentation shows incorrect server URLs:

1. Check the `APP_URL` in your `.env` file
2. Regenerate the documentation: `php artisan l5-swagger:generate`
3. Run the fix script: `php fix-api-docs.php`
4. Clear Laravel caches: `php artisan config:clear && php artisan cache:clear`

## Future Improvements

Consider implementing:
- Custom Artisan command for unified documentation generation
- Integration with CI/CD pipelines for automatic documentation updates
- Version-specific API documentation for different API releases
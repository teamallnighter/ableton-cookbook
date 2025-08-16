# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

The Ableton Cookbook is a Laravel-based social platform for Ableton Live users to share, discover, and rate workflow racks (.adg files). It features a custom PHP port of the Python Ableton rack analyzer, comprehensive social features, and a modern web interface built with Laravel Jetstream and Livewire.

## Core Development Commands

### Laravel Application Commands
```bash
# Navigate to Laravel app directory first
cd laravel-app

# Development server with all services
composer dev

# Alternative: Start individual services
php artisan serve                     # Laravel development server
php artisan queue:work                # Background job processing
php artisan pail --timeout=0         # Real-time log monitoring
npm run dev                          # Vite development server

# Testing
composer test                        # Run PHPUnit tests
php artisan test                     # Laravel-specific test runner
php artisan test --coverage         # Run tests with coverage

# Build and optimization
npm run build                        # Build frontend assets for production
php artisan optimize                 # Laravel optimization (routes, config, views)
php artisan config:cache             # Cache configuration
php artisan route:cache              # Cache routes
php artisan view:cache               # Cache blade templates

# Database operations
php artisan migrate                  # Run database migrations
php artisan migrate:fresh --seed    # Fresh migration with seeding
php artisan db:seed                  # Run database seeders

# Queue and cache management
php artisan queue:restart            # Restart queue workers
php artisan cache:clear              # Clear application cache
php artisan config:clear             # Clear configuration cache
php artisan route:clear              # Clear route cache
```

### Artisan Commands (Custom)
```bash
# Rack processing and maintenance
php artisan racks:reanalyze         # Re-analyze existing rack files
php artisan racks:update-editions   # Update Ableton edition detection
php artisan tags:cleanup            # Clean up unused tags

# Generate application key (first-time setup)
php artisan key:generate
```

## Architecture Overview

### Core Components

**Ableton Rack Analyzer** (`app/Services/AbletonRackAnalyzer/`)
- Pure PHP port of Python analyzer (no external dependencies)
- Analyzes .adg files by decompressing gzip and parsing XML
- Extracts rack metadata, devices, chains, macro controls, and version info
- Supports all rack types: Audio Effect, Instrument, and MIDI Effect racks
- Comprehensive device mapping for 200+ Ableton devices

**Models & Database** (`app/Models/`)
- `Rack`: Core model with analysis data, social features, file management
- `User`: Extended with social features (following, activity feeds)
- `RackRating`: 5-star rating system with reviews
- `Comment`: Threaded commenting system
- `Collection`: User-curated rack playlists
- `Tag`: Tagging system with usage tracking

**Controllers** (`app/Http/Controllers/`)
- **API Controllers** (`Api/`): RESTful endpoints for rack management, ratings, comments
- **Upload/Edit Controllers**: File upload processing and rack editing
- **Sitemap Controller**: SEO optimization with dynamic sitemaps

**Livewire Components** (`app/Livewire/`)
- `RackBrowser`: Advanced search and filtering interface
- `RackShow`: Rack detail page with social interactions
- `UserProfile`: User profile management and activity display
- `NotificationDropdown`: Real-time notification system

**Queue Jobs** (`app/Jobs/`)
- `ProcessRackFileJob`: Background rack file analysis
- `IncrementRackViewsJob`: Asynchronous view counting

**Services** (`app/Services/`)
- `RackProcessingService`: Orchestrates file upload and analysis
- `AbletonEditionDetector`: Detects Ableton Live edition from racks
- `RackCacheService`: Caching strategy for analyzed rack data
- `SeoService`: SEO optimization utilities

### Database Schema

**Primary Tables:**
- `racks`: File metadata, analysis results, social stats
- `users`: User profiles with social features
- `rack_ratings`: 5-star ratings with review text
- `comments`: Threaded discussions
- `tags` & `rack_tags`: Flexible tagging system
- `collections` & `collection_racks`: User playlists

**Social Features:**
- `follows`: User following relationships (via Overtrue package)
- `love_reactions`: Like system (via Laravel Love package)
- `user_activity_feeds`: Activity tracking
- `rack_downloads`: Download analytics
- `rack_favorites`: User favorites
- `notifications`: Real-time notifications

### Frontend Stack

**Core Technologies:**
- **Laravel Jetstream**: Authentication, teams, profile management
- **Livewire 3**: Reactive components without JavaScript complexity
- **Tailwind CSS 4**: Utility-first styling with latest features
- **Alpine.js**: Minimal JavaScript for enhanced interactions
- **Vite**: Modern asset bundling and HMR

**UI Components:**
- Responsive design with mobile-first approach
- Advanced search and filtering interfaces
- Real-time notifications and activity feeds
- File upload with drag-and-drop support
- Social interaction buttons (like, follow, rate)

## File Upload and Processing

### Upload Pipeline
1. File validation (format, size, malware scanning if configured)
2. Generate UUID and secure storage path
3. Queue background job for rack analysis
4. Parse .adg file using AbletonRackAnalyzer
5. Extract metadata, devices, chains, macro controls
6. Update database with analysis results
7. Generate SEO-optimized URLs and metadata

### Rack Analysis Process
- Decompress gzipped .adg files
- Parse XML structure using SimpleXML
- Map device types to human-readable names
- Extract chain information and routing
- Parse macro control assignments
- Detect Ableton Live version and edition
- Handle nested racks recursively

## Development Workflow

### Environment Setup
1. Ensure PHP 8.2+, Composer, Node.js 18+ are installed
2. Clone repository and navigate to `laravel-app/`
3. Run `composer install` and `npm install`
4. Copy `.env.example` to `.env` and configure
5. Generate application key: `php artisan key:generate`
6. Run migrations: `php artisan migrate`
7. Seed database: `php artisan db:seed`
8. Start development: `composer dev`

### Code Standards
- Follow Laravel conventions and PSR-12 standards
- Use Laravel Pint for code formatting: `./vendor/bin/pint`
- Write tests for new features in `tests/` directory
- Use type hints and return types consistently
- Follow RESTful API design patterns

### Performance Considerations
- **Caching**: Redis for sessions, cache, and queues
- **Queues**: All file processing happens in background jobs
- **Database**: Optimized indexes for social queries
- **Assets**: Vite for optimized frontend builds
- **Storage**: Private disk for secure file storage with signed URLs

### Security Features
- **File Validation**: Strict .adg format validation
- **Secure Storage**: Private storage with temporary signed URLs
- **Permissions**: Role-based access control with Spatie Permission
- **Rate Limiting**: API rate limiting to prevent abuse
- **CSRF Protection**: Built-in Laravel CSRF protection

## Testing Strategy

### Test Structure
- **Unit Tests**: Model logic, services, analyzers
- **Feature Tests**: HTTP endpoints, file uploads, user interactions
- **Database Tests**: Use in-memory SQLite for speed

### Key Test Areas
- Rack file analysis accuracy
- Upload and processing pipeline
- Social features (ratings, comments, follows)
- API endpoint functionality
- Permission and security checks

## Deployment Notes

### Production Requirements
- PHP 8.2+ with required extensions
- MySQL 8.0+ or PostgreSQL 13+
- Redis for caching and queues
- File storage (local or S3-compatible)
- Queue worker processes (Supervisor recommended)

### Environment Variables
- `FILESYSTEM_DISK`: Set to 'private' for local or 's3' for cloud storage
- `QUEUE_CONNECTION`: Use 'redis' for production
- `CACHE_STORE`: Use 'redis' for production
- Configure mail settings for notifications

### Monitoring
- Laravel Horizon for queue monitoring
- Laravel Telescope for debugging (disable in production)
- Application logs in `storage/logs/`
- Monitor queue workers and restart on deployment

## API Documentation

### Public Endpoints
- `GET /api/v1/racks` - Browse racks with advanced filtering
- `GET /api/v1/racks/{rack}` - Rack details and metadata
- `GET /api/v1/users/{user}` - User profiles and statistics

### Authenticated Endpoints
- `POST /api/v1/racks` - Upload new rack
- `POST /api/v1/racks/{rack}/rate` - Rate rack (1-5 stars)
- `POST /api/v1/racks/{rack}/download` - Generate download URL
- `POST /api/v1/users/{user}/follow` - Follow/unfollow users

All API responses follow JSON:API standards with consistent error handling and validation messages.
# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

The Ableton Cookbook is a sophisticated Laravel-based social platform for Ableton Live users to share, discover, and rate workflow racks (.adg files). It features a **pure PHP port** of the Python Ableton rack analyzer, comprehensive social features, and a modern web interface built with Laravel Jetstream and Livewire.

**Key Distinguishing Features:**
- Pure PHP .adg file analyzer (no Python dependencies)
- Real-time rack analysis and metadata extraction
- Social platform with ratings, comments, follows, and collections
- Advanced search and filtering by devices, rack types, and tags
- Secure file handling with temporary signed download URLs

## Development Commands

### Essential Development Workflow
```bash
# IMPORTANT: Always navigate to Laravel app directory first
cd laravel-app

# Start complete development environment (recommended)
composer dev
# This runs: Laravel server, queue worker, log monitoring, and Vite dev server

# Alternative: Start services individually
php artisan serve                     # Laravel development server
php artisan queue:work                # Background job processing  
php artisan pail --timeout=0         # Real-time log monitoring
npm run dev                          # Vite development server with HMR
```

### Testing and Quality
```bash
# Testing
composer test                        # Run PHPUnit test suite
php artisan test                     # Laravel-specific test runner
php artisan test --coverage         # Run tests with coverage report

# Code quality
./vendor/bin/pint                    # Laravel Pint code formatter
```

### Database Operations
```bash
# Database management
php artisan migrate                  # Run pending migrations
php artisan migrate:fresh --seed    # Fresh database with sample data
php artisan db:seed                  # Run database seeders only

# Cache management
php artisan optimize                 # Laravel optimization (production)
php artisan cache:clear              # Clear application cache
php artisan config:clear             # Clear configuration cache
php artisan queue:restart            # Restart queue workers
```

### Custom Artisan Commands
```bash
# Rack processing and maintenance
php artisan racks:reanalyze         # Re-analyze existing rack files
php artisan racks:update-editions   # Update Ableton edition detection
php artisan tags:cleanup            # Clean up unused tags

# First-time setup
php artisan key:generate             # Generate application key
```

### Build and Deployment
```bash
# Production builds
npm run build                        # Build frontend assets for production
php artisan config:cache             # Cache configuration for performance
php artisan route:cache              # Cache routes for performance
php artisan view:cache               # Cache Blade templates
```

## Architecture Overview

### Core System: Ableton Rack Analyzer
**Location:** `app/Services/AbletonRackAnalyzer/`

The heart of this application is a **pure PHP port** of a Python Ableton rack analyzer. This system:
- Decompresses gzipped .adg files using PHP's native compression
- Parses XML structure to extract rack metadata
- Maps 200+ Ableton device types to human-readable names
- Extracts device chains, macro controls, and version information
- Supports all rack types: Audio Effect, Instrument, and MIDI Effect racks

**Key Classes:**
- `AbletonRackAnalyzer`: Core parsing logic with comprehensive device mapping
- `RackProcessingService`: Orchestrates file upload, analysis, and database updates
- `AbletonEditionDetector`: Determines required Ableton Live edition based on devices

### Models and Database Design

**Primary Models:**
- `Rack`: Core model with analysis results, social metrics, and file management
- `User`: Extended with social features (following, activity feeds, statistics)
- `RackRating`: 5-star rating system with optional review text
- `Comment`: Threaded commenting system for community discussions
- `Collection`: User-curated playlists of racks
- `Tag`: Flexible tagging system with usage tracking

**Social Features:**
- User following relationships (via Overtrue Laravel Follow package)
- Like/reaction system (via Laravel Love package) 
- Activity feeds and real-time notifications
- Download tracking and analytics

### Processing Pipeline

**File Upload Flow:**
1. File validation (.adg format, size limits, malware scanning ready)
2. Generate UUID and store in private storage with SHA-256 hash
3. Queue `ProcessRackFileJob` for background analysis
4. Parse .adg file using `AbletonRackAnalyzer`
5. Extract metadata, devices, chains, and macro controls
6. Update database with analysis results and SEO-optimized metadata
7. Auto-approve or flag for moderation based on parsing success

**Background Jobs:**
- `ProcessRackFileJob`: Main rack file analysis (5-minute timeout, 3 retries)
- `IncrementRackViewsJob`: Asynchronous view counting for performance

### Frontend Stack

**Technologies:**
- **Laravel Jetstream**: Authentication, teams, profile management
- **Livewire 3**: Reactive components with real-time updates
- **Tailwind CSS 4**: Modern utility-first styling
- **Alpine.js**: Minimal JavaScript for enhanced interactions
- **Vite**: Fast asset bundling with hot module replacement

**Key Livewire Components:**
- `RackBrowser`: Advanced search and filtering interface
- `RackShow`: Rack detail page with social interactions
- `UserProfile`: User profile management and statistics
- `NotificationDropdown`: Real-time notification system

## File Structure and Key Locations

**Main Application:** All Laravel code is in the `laravel-app/` directory.

**Important Directories:**
- `app/Services/AbletonRackAnalyzer/`: Core .adg file parsing logic
- `app/Jobs/`: Background job processors for rack analysis
- `app/Livewire/`: Interactive UI components
- `app/Console/Commands/`: Custom Artisan commands for maintenance
- `storage/app/private/`: Secure file storage for uploaded .adg files

**Configuration Files:**
- `laravel-app/composer.json`: PHP dependencies and custom scripts
- `laravel-app/package.json`: Node.js dependencies for frontend
- `laravel-app/.env.example`: Environment configuration template

## Development Workflow Specifics

### Environment Setup
1. Ensure PHP 8.2+, Composer, Node.js 18+ are installed
2. Clone repository and **navigate to `laravel-app/` directory**
3. Run `composer install` and `npm install`
4. Copy `.env.example` to `.env` and configure database/storage
5. Generate application key: `php artisan key:generate`
6. Run migrations and seeders: `php artisan migrate:fresh --seed`
7. Start development: `composer dev`

### Working with .adg Files
- Files are stored in `storage/app/private/racks/` with UUID names
- Access via temporary signed URLs for security
- Use `RackProcessingService` for all upload operations
- Test rack analysis with existing sample files in the database

### Testing Strategy
- Unit tests for the `AbletonRackAnalyzer` parsing logic
- Feature tests for file upload and processing pipeline
- Social feature tests (ratings, comments, follows)
- Database tests use in-memory SQLite for speed

### Performance Considerations
- Redis caching for parsed rack data and user statistics
- Queue processing for all file analysis (never block requests)
- Database indexes optimized for social queries and searching
- Private storage with CDN-ready signed URL generation

## Security and Storage

**File Security:**
- Strict .adg format validation with file header checks
- Private storage disk prevents direct file access
- Temporary signed URLs (5-minute expiration) for downloads
- SHA-256 hashing prevents duplicate uploads
- Ready for antivirus integration (ClamAV support in deployment scripts)

**Data Security:**
- Role-based permissions via Spatie Permission package
- CSRF protection on all forms
- Rate limiting on API endpoints
- Secure user authentication via Laravel Jetstream

## Production Deployment

The project includes comprehensive production deployment scripts in the root directory:
- Complete Ubuntu server setup with Nginx, PHP-FPM, MySQL, Redis
- SSL certificate automation with Let's Encrypt
- Performance optimization and monitoring setup
- Database migration from SQLite to MySQL with data preservation

Refer to `docs/DEPLOYMENT_GUIDE.md` for detailed production deployment instructions.

## API and Integration

**Public API Endpoints:**
- `GET /api/v1/racks` - Browse racks with advanced filtering
- `GET /api/v1/racks/{rack}` - Rack details and metadata

**Authenticated Endpoints:**
- `POST /api/v1/racks` - Upload and process new rack
- `POST /api/v1/racks/{rack}/download` - Generate secure download URL
- `POST /api/v1/racks/{rack}/rate` - Rate rack (1-5 stars)

All API responses follow JSON:API standards with consistent error handling.

## Important Notes for Development

1. **Always work in `laravel-app/` directory** - This is where all Laravel code lives
2. **Use `composer dev`** for development - It starts all required services concurrently
3. **Queue workers are essential** - Rack processing happens in background jobs
4. **File storage is private** - Never expose .adg files directly; use signed URLs
5. **Redis is required** - Used for caching, sessions, and queue processing
6. **Tests use SQLite** - In-memory database for fast test execution

This is a production-ready social platform with sophisticated file processing capabilities, built following Laravel best practices and optimized for performance at scale.

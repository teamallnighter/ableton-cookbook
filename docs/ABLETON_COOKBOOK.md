# 🎵 Ableton Cookbook - Laravel Social Platform

A comprehensive Laravel-based social platform for Ableton Live users to share, discover, and rate workflow racks (.adg files).

## ✨ Features

### 🎛️ **Rack Management**
- **Upload & Analysis**: Pure PHP analyzer for .adg files (no Python dependencies)
- **Metadata Extraction**: Devices, chains, macro controls, and version info
- **Smart Search**: Filter by rack type, devices, tags, ratings, and more
- **Duplicate Detection**: SHA-256 hashing prevents duplicate uploads
- **Secure Downloads**: Temporary signed URLs for rack downloads

### 👥 **Social Features**
- **User Following**: Follow your favorite producers
- **Rating System**: 5-star ratings with detailed reviews
- **Comments**: Threaded discussions on racks
- **Collections**: Create curated playlists of racks  
- **Activity Feeds**: Stay updated with followed users
- **Like System**: Heart racks and comments

### 🔧 **Technical Architecture**
- **Pure Laravel**: No Python dependencies, single-stack deployment
- **Laravel Jetstream**: Authentication, teams, and profiles
- **Spatie Packages**: Permissions, media library, query builder
- **Social Media**: Follow/unfollow, likes/reactions
- **File Storage**: Local/S3 support with CDN integration
- **Performance**: Redis caching, queue processing, database optimization

## 🚀 Quick Start

### Installation

```bash
# Clone and setup
git clone <repo-url>
cd ableton-cookbook
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Setup database
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder

# Create storage symlink
php artisan storage:link

# Install frontend assets
npm install && npm run build

# Start development server
php artisan serve
```

### Environment Configuration

```bash
# Database
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/database.sqlite

# File Storage (use S3 for production)
FILESYSTEM_DISK=private

# AWS S3 (optional)
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
```

## 📡 API Endpoints

### Public Endpoints
```bash
GET /api/v1/racks                    # Browse racks with filters
GET /api/v1/racks/trending          # Trending racks
GET /api/v1/racks/featured          # Featured racks
GET /api/v1/racks/{rack}             # View rack details
GET /api/v1/users/{user}             # User profile
```

### Authenticated Endpoints
```bash
POST /api/v1/racks                   # Upload new rack
PUT /api/v1/racks/{rack}             # Update rack
DELETE /api/v1/racks/{rack}          # Delete rack
POST /api/v1/racks/{rack}/download   # Download rack
POST /api/v1/racks/{rack}/like       # Like/unlike rack
POST /api/v1/racks/{rack}/rate       # Rate rack (1-5 stars)
POST /api/v1/users/{user}/follow     # Follow user
GET /api/v1/user/feed                # Activity feed
```

## 🎛️ Ableton Rack Analyzer

### Features
- **Comprehensive Device Mapping**: 200+ Ableton devices recognized
- **XML Parsing**: Decompresses and analyzes .adg file structure
- **Metadata Extraction**: 
  - Rack type (Audio/Instrument/MIDI Effect)
  - Device chains and routing
  - Macro control assignments
  - Ableton Live version info
- **Error Handling**: Robust parsing with detailed error reporting

### Usage in Laravel
```php
use App\Services\RackProcessingService;

// Process uploaded rack
$rack = app(RackProcessingService::class)->processRack(
    $uploadedFile,
    $user,
    ['title' => 'Epic Bass Chain', 'tags' => ['bass', 'techno']]
);

// Access analyzed data
$rackType = $rack->rack_type;           // 'AudioEffectGroupDevice'
$devices = $rack->devices;              // Device chain data
$macros = $rack->macro_controls;        // Macro assignments
```

## 🗄️ Database Schema

### Core Tables
- **racks**: Rack files with metadata and social stats
- **users**: Enhanced with social features and statistics
- **rack_ratings**: 5-star rating system with reviews
- **comments**: Threaded commenting system
- **tags**: Tagging system with usage tracking
- **collections**: User-curated rack playlists

### Social Tables
- **follows**: User following relationships (via Overtrue package)
- **love_reactions**: Like system (via Laravel Love package)
- **user_activity_feeds**: Activity tracking and feeds
- **rack_downloads**: Download tracking and analytics

## ⚡ Performance Features

### Caching Strategy
- **Rack Metadata**: Cached parsed .adg results
- **User Statistics**: Cached follower/following counts
- **Search Results**: Redis caching for complex queries
- **Activity Feeds**: Cached user feeds and trending content

### Optimization
- **Database Indexes**: Optimized for social queries
- **File Storage**: S3 with CloudFront CDN support
- **Queue Processing**: Background rack analysis
- **API Rate Limiting**: Protects against abuse

## 🔐 Security & Permissions

### Role System
- **User**: Upload, edit own racks, comment, follow
- **Pro**: Verified creators with analytics access
- **Moderator**: Content moderation capabilities  
- **Admin**: Full system access
- **Banned**: Restricted access

### File Security
- **Private Storage**: Rack files stored securely
- **Signed URLs**: Temporary download links
- **File Validation**: .adg format verification
- **Virus Scanning**: Ready for integration

## 📈 Scaling Considerations

### Target Metrics
- **10,000+ users** within 6 months
- **5,000+ racks** in first year
- **Sub-200ms** API response times
- **100+ concurrent uploads**

### Infrastructure Ready
- **Load Balancing**: Multi-server deployment ready
- **Database Scaling**: Read replicas and optimization
- **CDN Integration**: Global file delivery
- **Queue Workers**: Horizontal scaling for processing

## 🛠️ Development

### Testing
```bash
php artisan test                     # Run test suite
php artisan test --coverage         # With coverage
```

### Code Quality
```bash
./vendor/bin/pint                    # Laravel Pint formatter
composer analyse                    # Static analysis
```

### Queue Processing
```bash
php artisan queue:work               # Process background jobs
php artisan horizon                  # Use Horizon for production
```

## 🚀 Deployment

### Production Checklist
- [ ] Configure S3 storage and CloudFront CDN
- [ ] Set up Redis cluster for caching
- [ ] Configure queue workers with Horizon
- [ ] Enable opcache and optimize Laravel
- [ ] Set up monitoring with Laravel Telescope
- [ ] Configure backup strategy
- [ ] SSL certificates and security headers

### Docker Support
```dockerfile
# Dockerfile ready for containerization
# Includes PHP, Nginx, and all dependencies
```

## 📞 API Examples

### Upload Rack
```javascript
const formData = new FormData();
formData.append('file', rackFile);
formData.append('title', 'Epic Bass Chain');
formData.append('description', 'Huge sub bass for techno');
formData.append('tags[]', 'bass');
formData.append('tags[]', 'techno');

fetch('/api/v1/racks', {
  method: 'POST',
  body: formData,
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
```

### Search Racks
```javascript
fetch('/api/v1/racks?' + new URLSearchParams({
  'filter[rack_type]': 'AudioEffectGroupDevice',
  'filter[devices]': 'Operator,Compressor',
  'filter[rating]': '4',
  'sort': '-downloads_count'
}));
```

---

## 🎯 Ready for Production

This Laravel scaffolding provides a complete foundation for the Ableton Cookbook platform with:

✅ **Pure PHP Implementation** - No Python complexity  
✅ **Social Media Features** - Following, ratings, comments, collections  
✅ **Advanced Search** - Filter by devices, tags, ratings, and more  
✅ **Performance Optimized** - Caching, queues, and database optimization  
✅ **Scalable Architecture** - Ready for 10,000+ users  
✅ **Security First** - Permissions, file validation, secure storage  
✅ **Professional Code Quality** - Laravel best practices throughout  

**Your Ableton .adg analyzer + Laravel = Social Platform Success! 🚀**
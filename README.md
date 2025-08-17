# 🎵 Ableton Cookbook

A community-driven platform for sharing and discovering Ableton Live racks, techniques, and creative resources.

![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=flat&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=flat&logo=tailwind-css)
![Livewire](https://img.shields.io/badge/Livewire-3.x-4E56A6?style=flat&logo=livewire)

## 🌟 Features

### 🎛️ Rack Management
- **Upload & Share**: Share your custom Ableton Live racks with the community
- **Smart Analysis**: Automatic rack analysis including device detection and Ableton version compatibility
- **Categories & Tags**: Organize racks by genre, style, and device types
- **Rating System**: Community-driven rating and review system
- **Favorites**: Save and organize your favorite racks

### 👥 User System
- **Enhanced Registration**: Username-based accounts with email verification
- **User Profiles**: Customizable profiles with social media links and bio
- **Follow System**: Follow other users and get notified of new uploads
- **Activity Feed**: Stay updated with community activity

### 📧 Email System
- **Professional Email Templates**: Custom-branded verification and notification emails
- **GDPR Compliant**: Explicit email consent with granular preferences
- **Email Authentication**: Full SPF/DKIM/DMARC configuration for optimal deliverability
- **Notification System**: Configurable email notifications for various events

### 🔍 Discovery Features
- **Advanced Search**: Filter by device, genre, Ableton version, and more
- **Browse by Category**: Organized browsing experience
- **Trending Racks**: Discover popular and recently uploaded content
- **Recommendations**: Personalized rack suggestions

### 🛡️ Security & Performance
- **Two-Factor Authentication**: Optional 2FA for enhanced account security
- **Role-Based Permissions**: Admin and user role management
- **Performance Optimized**: Cached queries and optimized database indexes
- **SEO Optimized**: Full SEO implementation with structured data

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- Composer
- Node.js & NPM
- MySQL/MariaDB
- Web server (Apache/Nginx)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/teamallnighter/ableton-cookbook.git
   cd ableton-cookbook/laravel-app
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install && npm run build
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure your `.env` file**
   ```env
   APP_NAME="Ableton Cookbook"
   APP_URL=https://your-domain.com
   
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ableton_cookbook
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   
   # Email Configuration
   MAIL_MAILER=smtp
   MAIL_HOST=your-smtp-host
   MAIL_PORT=465
   MAIL_USERNAME=your-email@domain.com
   MAIL_PASSWORD=your-password
   MAIL_ENCRYPTION=ssl
   MAIL_FROM_ADDRESS=noreply@your-domain.com
   MAIL_FROM_NAME="Ableton Cookbook Team"
   ```

5. **Database setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. **Storage setup**
   ```bash
   php artisan storage:link
   ```

7. **Start the application**
   ```bash
   php artisan serve
   ```

## 📧 Email Configuration

The application includes a comprehensive email system with professional templates and authentication.

### DNS Records Required

Add these DNS records to your domain for optimal email deliverability:

```dns
# SPF Record
TXT @ "v=spf1 include:your-mail-provider.com ~all"

# DMARC Record  
TXT _dmarc "v=DMARC1; p=quarantine; rua=mailto:dmarc@your-domain.com; ruf=mailto:dmarc@your-domain.com; fo=1"

# DKIM Record (provided by your email service)
TXT selector._domainkey "v=DKIM1; k=rsa; p=YOUR_PUBLIC_KEY"
```

### Email Testing
```bash
php artisan email:test your-test-email@example.com
```

## 🔧 Development

### Key Commands
```bash
# Run tests
php artisan test

# Generate sitemap
php artisan sitemap:generate

# Optimize SEO
php artisan seo:optimize

# Clear caches
php artisan optimize:clear
```

### Project Structure
```
laravel-app/
├── app/
│   ├── Console/Commands/     # Custom Artisan commands
│   ├── Http/Controllers/     # Application controllers
│   ├── Models/              # Eloquent models
│   ├── Notifications/       # Email notifications
│   └── Services/           # Business logic services
├── database/
│   ├── migrations/         # Database migrations
│   └── seeders/           # Database seeders
├── resources/
│   ├── views/             # Blade templates
│   └── js/               # Frontend assets
└── routes/               # Application routes
```

## 🌐 Live Demo

Visit [ableton.recipes](https://ableton.recipes) to see the application in action.

## 📊 Key Features Implementation

### Registration System
- **Username Field**: Separate username from display name
- **Email Consent**: GDPR-compliant consent checkbox
- **Email Verification**: Custom-branded verification emails
- **Validation**: Comprehensive form validation with user feedback

### Email Authentication
- **SPF**: Authorizes sending servers
- **DKIM**: Cryptographic email signing  
- **DMARC**: Authentication policy enforcement
- **Professional Templates**: Custom-designed email templates

### Performance Optimizations
- **Database Indexing**: Optimized queries for large datasets
- **Caching**: Redis-based caching for improved performance
- **SEO**: Full search engine optimization implementation
- **Image Optimization**: Responsive image handling

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## 🔗 Links

- **Website**: [ableton.recipes](https://ableton.recipes)
- **Repository**: [GitHub](https://github.com/teamallnighter/ableton-cookbook)
- **Issues**: [GitHub Issues](https://github.com/teamallnighter/ableton-cookbook/issues)

## 🙏 Acknowledgments

- Built with [Laravel](https://laravel.com)
- UI components from [Tailwind CSS](https://tailwindcss.com)
- Real-time features powered by [Laravel Livewire](https://laravel-livewire.com)
- Icons from [Heroicons](https://heroicons.com)

---

**Made with ❤️ for the Ableton Live community**

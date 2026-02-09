# Technology Stack

## Programming Languages
- **PHP 8.2+**: Primary backend language
- **JavaScript (ES6+)**: Frontend scripting
- **SQL**: Database queries

## Core Framework
- **Laravel 12.0**: PHP web application framework
  - Eloquent ORM for database interactions
  - Blade templating engine
  - Artisan CLI for development tasks
  - Built-in authentication and authorization

## Key Dependencies

### Backend Packages
- **laravel/sanctum 4.2**: API token authentication
- **darkaonline/l5-swagger 9.0**: OpenAPI/Swagger documentation generation
- **intervention/image 3.11**: Image manipulation and processing
- **swiftmailer/swiftmailer 5.4**: Email sending functionality
- **laravel/tinker 2.10**: Interactive REPL for Laravel

### Development Tools
- **laravel/pail 1.2**: Real-time log viewing
- **laravel/pint 1.24**: PHP code style fixer
- **laravel/sail 1.41**: Docker development environment
- **phpunit/phpunit 11.5**: Testing framework
- **fakerphp/faker 1.23**: Fake data generation for testing
- **mockery/mockery 1.6**: Mocking framework for tests
- **nunomaduro/collision 8.6**: Error reporting for CLI

### Frontend Build Tools
- **Vite 7.0**: Frontend build tool and dev server
- **laravel-vite-plugin 2.0**: Laravel integration for Vite
- **Tailwind CSS 4.0**: Utility-first CSS framework
- **@tailwindcss/vite 4.0**: Vite plugin for Tailwind
- **Axios 1.11**: HTTP client for API requests
- **Concurrently 9.0**: Run multiple commands concurrently

## Database
- **SQLite**: Default database (development)
- **MySQL/PostgreSQL**: Production-ready alternatives (configured via .env)
- **Database Queue Driver**: Queue jobs stored in database

## Authentication & Security
- **Laravel Sanctum**: Token-based API authentication
- **Bearer Token**: Authorization header format
- **Token Expiry Middleware**: Custom token validation
- **BCRYPT**: Password hashing (12 rounds)

## API Documentation
- **OpenAPI 3.0**: API specification standard
- **Swagger UI**: Interactive API documentation interface
- **L5-Swagger**: Laravel package for Swagger integration
- Documentation available at `/api/documentation`

## File Storage
- **Local Filesystem**: Default storage driver
- **Public Disk**: Publicly accessible files
- **Private Disk**: Protected file storage
- **Document Storage**: `/public/document` directory for uploads

## Email System
- **SwiftMailer**: Email sending library
- **Dynamic Mailer Service**: User-specific SMTP configurations
- **Mail Queue**: Asynchronous email sending
- **Log Driver**: Development email logging

## Logging & Monitoring
- **Monolog**: Logging library
- **Stack Channel**: Multiple log handlers
- **Single Channel**: Single file logging (default)
- **Laravel Pail**: Real-time log streaming

## Development Environment

### System Requirements
- PHP >= 8.2
- Composer (dependency manager)
- Node.js >= 18 (for frontend assets)
- NPM or Yarn (package manager)
- SQLite extension enabled

### Environment Configuration
Configuration via `.env` file:
```
APP_NAME=Laravel
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=sqlite
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

MAIL_MAILER=log
```

## Build System

### Composer Scripts
```bash
# Development server with queue, logs, and Vite
composer dev

# Run tests
composer test

# Install dependencies
composer install

# Update dependencies
composer update
```

### NPM Scripts
```bash
# Start Vite dev server
npm run dev

# Build for production
npm run build
```

### Artisan Commands
```bash
# Start development server
php artisan serve

# Run database migrations
php artisan migrate

# Clear application cache
php artisan cache:clear

# Generate API documentation
php artisan l5-swagger:generate

# Run queue worker
php artisan queue:listen

# View real-time logs
php artisan pail

# Interactive REPL
php artisan tinker
```

## Development Workflow

### Concurrent Development Mode
The `composer dev` script runs multiple services simultaneously:
1. **PHP Development Server**: `php artisan serve` (port 8000)
2. **Queue Worker**: `php artisan queue:listen --tries=1`
3. **Log Viewer**: `php artisan pail --timeout=0`
4. **Vite Dev Server**: `npm run dev` (HMR enabled)

### Testing
- **PHPUnit**: Unit and feature tests
- **Test Database**: Separate SQLite database for testing
- **Faker**: Generate test data
- **Mockery**: Mock dependencies

## Code Quality Tools
- **Laravel Pint**: Opinionated PHP code formatter
- **EditorConfig**: Consistent coding styles across editors
- **Composer Autoloader Optimization**: Production performance

## Deployment Considerations
- **Autoloader Optimization**: `optimize-autoloader: true`
- **Asset Building**: `npm run build` for production
- **Environment Variables**: Configure via `.env` file
- **Queue Workers**: Run as background processes
- **Log Rotation**: Configure for production logs
- **Cache Configuration**: Redis recommended for production

## API Versioning
- Currently unversioned (implicit v1)
- All endpoints under `/api` prefix
- Future versions can use `/api/v2` pattern

## Performance Optimization
- **Database Indexing**: Migrations include indexes
- **Query Optimization**: Eager loading relationships
- **Caching**: Database cache driver (Redis recommended for production)
- **Queue Jobs**: Asynchronous processing for heavy tasks
- **Response Compression**: Gzip enabled

## Security Features
- **CSRF Protection**: Enabled for web routes
- **SQL Injection Prevention**: Eloquent ORM parameterized queries
- **XSS Protection**: Blade template escaping
- **Password Hashing**: BCRYPT with 12 rounds
- **Token Expiry**: Custom middleware for API tokens
- **CORS Support**: Configured via middleware

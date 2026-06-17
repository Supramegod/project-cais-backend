# CAIS Backend

> **C**ustomer **A**cquisition & **I**ntegrated **S**ervices — Backend API  
> Multi-tenant business management system for Sales, HR, Quotation, PKS, and SPK operations.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 12 |
| Language | PHP ^8.2 |
| Database | SQLite (dev) / MySQL (prod) |
| Cache | Database / Redis |
| Queue | Database / Redis |
| Auth | Laravel Sanctum (token-based) |
| API Docs | L5-Swagger (`@OA` attributes) |
| Frontend | Vite + Tailwind CSS 4 |
| Container | Docker + Docker Compose |
| CI/CD | GitLab CI |

## Features / Modules

- **Leads Management** — Track and manage sales leads, assign sales teams, import/export
- **Quotation Engine** — Multi-step quotation creation, approval workflow, revision/duplication, PDF export, multi-site support, complex cost/margin calculations
- **PKS (Perjanjian Kerja Sama)** — Contract management with version history, comparison, approval & activation
- **SPK (Surat Perintah Kerja)** — Work order management with site assignments, file upload, checklist submission
- **Sales Activity** — Daily sales activity logging with file attachments
- **Sales Target & Revenue** — KPI tracking, revenue summaries, monthly/weekly reports
- **Customer Management** — Company groups, customer activity tracking, email notifications
- **HR Master Data** — Wages (UMP, UMK, UMSK, UMSP), salary rules, position tunjangan, THR rules
- **Master Data** — Barang, jenis perusahaan, jenis barang, bentuk usaha, TOP, management fee, supplier, training, positions
- **Role & Permissions** — Menu-based role management with granular permissions
- **Dashboard** — Approval dashboard, PKS monitoring, notifications
- **System Announcements** — Rich content announcements with image uploads and file attachments
- **Admin Panel** — Direct step management for quotations
- **Submission** — Sales submission management with Google Sheet sync (V2)

## Prerequisites

- PHP 8.2+
- Composer 2.x
- Node.js 20+ / npm
- SQLite (development) or MySQL 8+ (production)
- Redis (optional, for cache/queue)

## Setup

```bash
# 1. Clone & install dependencies
git clone <repo-url> project-cais-backend
cd project-cais-backend
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database (SQLite — default for dev)
# SQLite is already configured in .env.example; just run:
php artisan migrate

# 4. Storage link
php artisan storage:link

# 5. Start development
composer dev
```

## Available Commands

### Development (all-in-one)
```bash
composer dev
# Runs 4 services concurrently:
#   - php artisan serve       (:8000)
#   - php artisan queue:listen --tries=1
#   - php artisan pail --timeout=0  (real-time logs)
#   - npm run dev             (Vite HMR)
```

### Individual Services
```bash
php artisan serve                     # API server on :8000
php artisan queue:listen --tries=1    # Queue worker
php artisan pail --timeout=0          # Real-time logs
npm run dev                            # Vite HMR
npm run build                          # Production build
```

### Testing
```bash
composer test                         # Full suite (config:clear + php artisan test)
php artisan test --filter=TestName    # Single test
php artisan test tests/Feature        # Feature tests only
php artisan test tests/Unit           # Unit tests only
```

## Architecture

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  Routes      │────▶│  Controllers  │────▶│  Services     │
│  (api.php)   │     │  (thin)       │     │  (logic)      │
└──────────────┘     └──────────────┘     └──────┬───────┘
       │                                         │
       ▼                                         ▼
┌──────────────────┐                  ┌──────────────────┐
│  BaseRequest     │                  │  DTOs / Models   │
│  (422 format)    │                  │  (data layer)    │
└──────────────────┘                  └──────────────────┘
```

- **Controllers** — Thin, handle HTTP concerns only. Delegate to Services.
- **Services** (`app/Services/`) — Business logic, DB transactions, return DTOs or arrays.
- **DTOs** (`app/DTO/`) — Decouple calculation results from Eloquent models.
- **Requests** (`app/Http/Requests/`) — Extend `BaseRequest` for consistent 422 format: `{ "message": { "field": ["error"] } }`.
- **Resources** (`app/Http/Resources/`) — API transformers for consistent JSON: `{ "success": true, "data": {}, "message": "..." }`.
- **Models** (`app/Models/`) — 80+ Eloquent models, all with `SoftDeletes` + audit columns.

## API Documentation

API is documented via L5-Swagger using `@OA` PHP attributes on controllers.

```bash
# Generate Swagger docs
php artisan l5-swagger:generate

# View docs (dev)
# http://localhost:8000/api/documentation
```

## Authentication

Uses **Laravel Sanctum** with token-based auth:
- Login → `POST /api/auth/login` → returns token
- All protected routes use `auth:sanctum,web` middleware
- Custom `token.expiry` middleware checks `expires_at` on Sanctum tokens
- Refresh tokens available via `POST /api/auth/refresh`
- Custom token model: `HrisPersonalAccessToken`

## Docker

```bash
# Development
docker-compose -f docker-compose-dev.yml up

# Services:
#   - cais-v2-be-dev          (PHP-FPM :9000)
#   - cais-v2-queue-worker-dev (queue worker)
# Network: shelter-network (external, must exist)
```

## CI/CD

GitLab CI with two environments:

```
test (php:8.2-cli) → deploy-dev (development branch) → deploy-prod (prod branch)
```

Deploy runs automatically:
- `php artisan migrate --force`
- `php artisan l5-swagger:generate`
- `php artisan optimize:clear`

## Testing

- **Database**: SQLite `:memory:` (fast)
- **Cache/Session**: `array` driver
- **Queue**: `sync` driver (immediate execution)
- **Mail**: `array` driver (no SMTP)

```bash
composer test   # runs config:clear first
```

## Directory Structure

```
├── app/
│   ├── DTO/                      # Data transfer objects
│   ├── Http/
│   │   ├── Controllers/          # Thin controllers (46 files)
│   │   ├── Middleware/           # CheckTokenExpiry, ApiResponseMiddleware
│   │   ├── Requests/             # Validation (extend BaseRequest)
│   │   └── Resources/            # API transformers (3 classes)
│   ├── Models/                   # 80+ Eloquent models
│   └── Services/                 # Business logic (13 services)
├── bootstrap/
│   └── app.php                   # Middleware, Sanctum config
├── config/                       # Laravel config files
├── database/
│   └── migrations/               # Schema migrations
├── routes/
│   └── api.php                   # All API routes (576 lines)
├── tests/
│   ├── Feature/                  # HTTP endpoint tests
│   └── Unit/                     # Business logic tests
├── docker-compose-dev.yml        # Dev Docker setup
├── docker-compose-prod.yml       # Prod Docker setup
├── .gitlab-ci.yml                # CI/CD pipeline
└── AGENTS.md                     # AI development instructions
```

## Environment Variables

Key `.env` variables:

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_NAME` | `Laravel` | Application name |
| `APP_ENV` | `local` | Environment (`local`, `production`, `testing`) |
| `APP_DEBUG` | `true` | Enable debug mode |
| `APP_URL` | `http://localhost` | Application URL |
| `DB_CONNECTION` | `sqlite` | Database driver (`sqlite`, `mysql`) |
| `SESSION_DRIVER` | `database` | Session driver |
| `QUEUE_CONNECTION` | `database` | Queue driver |
| `CACHE_STORE` | `database` | Cache driver |
| `MAIL_MAILER` | `log` | Mail driver |

## Key Conventions

- **SoftDeletes** on every model — never use `forceDelete()` unless archival cleanup
- **Audit columns**: always populate `created_by`, `updated_by`, `deleted_by`
- **Table prefix**: `sl_*` (e.g., `sl_leads`, `sl_quotation`)
- **Scopes**: use local scopes (`.active()`, `.byBranch($id)`)
- **LeadsKebutuhan pivot**: use `updateOrCreate`, NOT `firstOrCreate` (prevents duplicates)
- **Multi-site**: `$request->jumlah_site == "Single Site"` is case-sensitive

## License

Proprietary — shelterapp2.co.id

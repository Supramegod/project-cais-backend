# CAIS Backend - AI Development Instructions

> **Project**: CAIS Backend v2 | **Framework**: Laravel 12 | **Tech Stack**: PHP 8.2, MySQL, Redis, Docker | **Purpose**: Multi-tenant business management system with Sales, HR, Quotation, and Supplier modules

---

## Quick Reference

### Essential Commands
```bash
# Development environment (server + queue + logs + frontend bundler)
composer dev

# Run tests (clears config, uses SQLite in-memory)
composer test

# Individual services
php artisan serve                          # Dev server on :8000
php artisan queue:listen --tries=1         # Queue worker
php artisan pail --timeout=0               # Real-time logs
npm run dev                                # Vite frontend dev

# Production build
npm run build
```

### Project Structure
```
app/
  Models/              # 80+ Eloquent models with relationships
  Services/           # Business logic layer (QuotationService, SalesRevenueService, etc.)
  DTO/               # Data transfer objects for API responses (decoupled from models)
  Http/Requests/     # Validation classes with custom 422 error format
  Http/Resources/    # API response transformers
  Http/Controllers/  # Thin controllers, delegate to Services
  Events/            # Model events (QuotationCreated, etc.)
  Listeners/         # Event handlers (ProcessQuotationDuplication)
database/
  migrations/        # Schema (2 recent 2026-02 migrations, init from backup)
  seeders/          # Database seeders
routes/
  api.php           # REST API endpoints (auth + resource routes)
tests/
  Feature/          # HTTP endpoint tests
  Unit/             # Business logic tests
```

---

## Architecture & Patterns

### Core Models
```
Quotation       → QuotationDetail, QuotationSite, QuotationPic, QuotationMargin
Leads           → LeadsKebutuhan, SalesActivity, SalesActivityFile
Kebutuhan       → KebutuhanDetail, KebutuhanDetailTunjangan
Customer        → CustomerActivity, CustomerActivityFile
```

### Naming Conventions
| Layer | Pattern | Example |
|-------|---------|---------|
| **Database** | `sl_*` prefix, snake_case | `sl_quotation`, `sl_customer_activity` |
| **Models** | PascalCase | `Quotation`, `CustomerActivity` |
| **Controllers** | `{Domain}Controller` | `QuotationController`, `LeadsController` |
| **Services** | `{Domain}Service` | `QuotationBusinessService`, `SalesRevenueService` |
| **Requests** | `{Action}{Domain}Request` | `QuotationStoreRequest`, `QuotationApproveRequest` |
| **Resources** | `{Domain}Resource` | `QuotationResource`, `QuotationCollection` |
| **Scopes** | Snake_case methods | `->active()`, `->byBranch($id)` |

### Model Conventions
- **SoftDeletes**: All models use soft delete (`deleted_at` column, not permanent deletion)
- **Audit Trail**: Track with `created_by`, `updated_by`, `deleted_by` columns
- **Timestamps**: All models include `created_at`, `updated_at`
- **Scopes**: Use Eloquent local scopes (`.active()`, `.byBranch()`, etc.)
- **Relationships**: Explicit `belongsTo()`, `hasMany()`, `hasManyThrough()`

### Service Layer
Services contain **business logic** and manage complexity:
- Injected into controllers via dependency injection
- Use Eloquent queries directly on models
- Return prepared data arrays or DTO objects (not raw models to APIs)
- Handle transactions for multi-step operations
- Examples:
  - `QuotationBusinessService` - quotation prep + site creation + calculations
  - `QuotationDuplicationService` - revision cloning with change tracking
  - `QuotationNotificationService` - email workflows
  - `SalesRevenueService` - complex revenue calculations

### DTOs (Data Transfer Objects)
Decouples calculation results from database models:
```php
// DTO objects for API responses (not Eloquent models)
CalculationSummary          // Totals
DetailCalculation          // Line-item calculations
QuotationCalculationResult // Aggregated result
```
- Instantiate in Services, return from Controllers
- API Resources transform these for JSON output

### API Layer

**Request Validation** (`app/Http/Requests/`):
- Extend `BaseRequest` (custom validation)
- Returns 422 with error messages: `{ "message": "...", "errors": {} }`
- Example:
  ```php
  class QuotationStoreRequest extends BaseRequest {
      public function rules() { ... }
      public function authorize() { ... }
  }
  ```

**Response Format** (Resources transform models):
```json
{
  "message": "Success|error message",
  "data": {} 
}
```

**Route Groups**:
- `/auth` - Sanctum authentication endpoints
- `/jenis-perusahaan`, `/jenis-barang`, etc. - Standard RESTful CRUD
- `/quotation` - Complex workflow (store, copy, approve, reject, etc.)
- All use `auth:sanctum` middleware + custom `token.expiry` middleware

### Authentication
- **Sanctum**: Token-based auth (stateless, API-first)
- **Custom Middleware**: `token.expiry` validates token expiry window
- Check `app/Http/Middleware/` for implementation

---

## Development Workflows

### Adding a New Feature

1. **Create the Model**:
   ```bash
   php artisan make:model {Name} --migration --factory --seeder
   # Inherits SoftDeletes, adds audit columns (created_by, updated_by, deleted_by)
   ```

2. **Write Validation Request**:
   ```bash
   php artisan make:request {Action}{Name}Request
   # Extend BaseRequest for 422 error format
   ```

3. **Build Service Layer**:
   ```bash
   php artisan make:service {Name}Service
   # Place business logic here, inject into controller
   ```

4. **Create Controller**:
   ```bash
   php artisan make:controller {Name}Controller --resource
   # Inject Service, delegate logic to Service layer
   ```

5. **Add API Resource**:
   ```bash
   php artisan make:resource {Name}Resource
   # Transform model/DTO to JSON response
   ```

6. **Define Routes** (`routes/api.php`):
   ```php
   Route::apiResource('names', {Name}Controller)->middleware('auth:sanctum');
   ```

7. **Write Tests**:
   - Feature tests in `tests/Feature/` (HTTP endpoints)
   - Unit tests in `tests/Unit/` (Services, DTOs)

### Modifying Existing Data
- Always use migrations for schema changes:
  ```bash
  php artisan make:migration {change_description}
  ```
- Migrations run in `docker-compose-dev.yml` on startup

### Common Issues & Solutions

| Problem | Cause | Solution |
|---------|-------|----------|
| **Storage directory permission errors** | Dockerfile not executed | Ensure `chmod -R 775 storage bootstrap/cache` is applied |
| **Queue jobs not running** | Queue worker not started | Run `composer dev` or manually start `php artisan queue:listen` |
| **Token expired on requests** | Custom middleware beyond Laravel Sanctum | Check `app/Http/Middleware/TokenExpiry.php` for expiry window |
| **Multi-site validation fails** | Field comparison case-sensitive | Check `$request->jumlah_site == "Multi Site"` (exact string match) |
| **Vite CSS/JS not loading** | Vite dev server not running | Ensure `npm run dev` is active (part of `composer dev`) |
| **Tests timeout** | Slow database setup | SQLite in-memory is configured in `phpunit.xml` (faster) |
| **Composer timeout** | Long package downloads | `process-timeout: 900` (15m) is set in composer.json |
| **.env missing** | Dockerfile-dev doesn't copy .env.example | Manually: `cp .env.example .env && php artisan key:generate` |

---

## Testing

**Configuration** (`phpunit.xml`):
- Database: SQLite in-memory (`:memory:`) for speed
- Cache/Session: Array drivers
- Mail: Array driver (no external SMTP)
- Queue: Sync driver (execute immediately)

**Running Tests**:
```bash
composer test                    # Full suite (clears config first)
php artisan test tests/Unit      # Unit tests only
php artisan test tests/Feature   # Feature tests only
php artisan test --filter=TestName  # Specific test
```

**Test Structure**:
```php
// tests/Feature/QuotationTest.php
class QuotationTest extends TestCase {
    public function test_create_quotation() {
        $response = $this->postJson('/api/quotation', [ ... ]);
        $response->assertStatus(201);
    }
}

// tests/Unit/QuotationServiceTest.php
class QuotationServiceTest extends TestCase {
    public function test_calculate_margin() {
        $service = new QuotationService();
        $result = $service->calculateMargin(...);
        $this->assertEquals(expected, $result);
    }
}
```

---

## Docker Deployment

### Development Setup
```bash
docker-compose -f docker-compose-dev.yml up

# Services:
#   - cais-v2-be-dev (PHP-FPM:9000)
#   - cais-v2-queue-worker-dev (async queue processor)
#   - Network: shelter-network (external, must exist)
```

### Production Setup
- Use `docker-compose.yml` and `docker-compose-prod.yml`
- Dockerfile is optimized (single-stage, includes `storage:link`)
- Ensure `.env` has production database credentials

### Environment Setup
```bash
cp .env.example .env
php artisan key:generate
php artisan storage:link     # Symlink storage/app/public → public/storage
php artisan cache:clear      # Clear stale caches
```

---

## Key Files & References

| File | Purpose |
|------|---------|
| [composer.json](composer.json) | PHP dependencies, build scripts |
| [package.json](package.json) | Frontend (Vite, npm scripts) |
| [routes/api.php](routes/api.php) | API endpoint definitions |
| [app/Services/](app/Services/) | Business logic (start here for feature changes) |
| [app/Http/Requests/](app/Http/Requests/) | Input validation |
| [app/Http/Resources/](app/Http/Resources/) | API response formatting |
| [database/migrations/](database/migrations/) | Schema changes |
| [phpunit.xml](phpunit.xml) | Test configuration |
| [Dockerfile-dev](Dockerfile-dev) | Development container image |

---

## Tips for AI Agents

1. **Start with Services**: Most business logic lives in `app/Services/` — modifications often start here
2. **Follow the SoftDelete pattern**: Don't write `->forceDelete()` unless archival cleanup is needed
3. **Use DTOs for complex calculations**: Don't return raw Eloquent models from Services to API
4. **Validate in Requests, not Controllers**: Thin controller with validation delegated to Request classes
5. **Check audit columns**: Always populate `created_by`, `updated_by`, `deleted_by` when modifying data
6. **Test first**: Write test cases before implementing features (TDD approach preferred)
7. **Scope queries**: Use Eloquent scopes (`.active()`, `.byBranch()`) for readability
8. **Queue long operations**: Use `dispatch()` for events that should run async (e.g., email notifications)
9. **Multi-site complexity**: Quotations support multi-site; check `QuotationSite` relationship
10. **Swagger docs**: API is documented with L5-Swagger; check `@OA\` PHP attributes in controllers

---

**Last Updated**: 2026-02-10 | **Laravel Version**: 12.0 | **PHP**: 8.2+

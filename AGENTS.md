# AGENTS.md

> CAIS Backend | Laravel 12 | PHP 8.2+ | Multi-tenant business management (Sales, HR, Quotation, PKS, SPK)

## Essential Commands

```bash
composer dev        # Dev: server + queue + logs + Vite (runs 4 services concurrently)
composer test       # Clears config then runs: php artisan test
php artisan serve   # API server on :8000
npm run dev         # Vite frontend (HMR)
npm run build       # Production build

# Individual test runs
php artisan test tests/Feature   # Feature tests only
php artisan test tests/Unit      # Unit tests only
php artisan test --filter=Name   # Single test
```

## Architecture

### Service Layer Pattern
Controllers delegate to Services; Services contain business logic:
- Controllers: thin, only HTTP concerns (validation response, JSON)
- Services: business rules, transactions, return data arrays/DTOs
- Key Services: `QuotationBusinessService`, `QuotationDuplicationService`, `SalesRevenueService`

### DTOs (`app/DTO/`)
Decouple calculation results from models:
- `CalculationSummary`, `DetailCalculation`, `QuotationCalculationResult`
- Instantiate in Services, return from Controllers, transform in Resources

### Model Conventions
- **SoftDeletes**: All models use `SoftDeletes` trait (`deleted_at` column)
- **Audit columns**: `created_by`, `updated_by`, `deleted_by`
- **Relationships**: Explicit `belongsTo`, `hasMany`, `hasManyThrough`
- **Scopes**: Eloquent local scopes (`.active()`, `.byBranch($id)`)
- **Database prefix**: Tables use `sl_*` prefix (e.g., `sl_leads`, `sl_quotation`)

### Request Validation
- Extend `BaseRequest` (not direct `FormRequest`) for consistent 422 error format
- Custom validation returns: `{ "message": { "field": ["error"] } }`

### API Response Format
```json
{ "success": true, "data": {}, "message": "..." }
{ "success": false, "message": "error", "error": "detail" }
```

## Critical Quirks

### Token Authentication
- Uses `auth:sanctum,web` + custom `token.expiry` middleware
- `CheckTokenExpiry` middleware checks token expiration (not session)
- Custom model: `HrisPersonalAccessToken` (configured in `bootstrap/app.php`)

### Quotation Multi-Site Validation
- `$request->jumlah_site == "Single Site"` (exact string match, case-sensitive)
- Site array fields (`multisite`, `provinsi_multi`, etc.) default to `[]` when empty

### LeadsKebutuhan Relationship
- Uses `updateOrCreate`, NOT `firstOrCreate` with composite unique keys
- Relationship must filter soft deletes: `->wherePivot('deleted_at', null)`
- See `app/Models/Leads.php:77-86` for correct pattern

### Leads.assignSales() Known Fixes
The `assignSales()` method had these bugs that are now fixed:
1. N+1 queries: Pre-load Kebutuhan with `whereIn()` before loop
2. Duplicates: Use `updateOrCreate` with minimal unique key
3. Soft deletes: Filter `deleted_at` in `leadsKebutuhan()` relationship
4. Activity log: Collect all sales names before creating single log entry

## Testing

### Configuration (`phpunit.xml`)
- Database: SQLite `:memory:` (fast)
- Cache/Session/Queue: `array`/`sync` drivers
- Mail: `array` driver (no SMTP)

### Running Tests
```bash
composer test                    # Full suite
php artisan test --filter=TestName
```

## Docker Development

```bash
docker-compose -f docker-compose-dev.yml up
# Services: cais-v2-be-dev (PHP-FPM) + cais-v2-queue-worker-dev
# Network: shelter-network (external, must exist)
```

## Key Files

| Path | Purpose |
|------|---------|
| `app/Services/` | Business logic (start here for features) |
| `app/Http/Controllers/` | Thin controllers (delegate to Services) |
| `app/Http/Requests/` | Validation (extend `BaseRequest`) |
| `app/Models/` | 80+ Eloquent models |
| `routes/api.php` | API routes (auth + resource routes) |
| `bootstrap/app.php` | Custom middleware registration |

## Common Issues

| Problem | Solution |
|---------|----------|
| Storage permission errors | `chmod -R 775 storage bootstrap/cache` |
| Queue jobs not running | Start `php artisan queue:listen` |
| Token expired immediately | Check `CheckTokenExpiry` middleware |
| Tests timeout | SQLite `:memory:` is default (fast) |
| .env missing | `cp .env.example .env && php artisan key:generate` |

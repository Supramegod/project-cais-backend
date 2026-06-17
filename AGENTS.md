# AGENTS.md

> Laravel 12 | PHP ^8.2 | Multi-tenant business management (Sales, HR, Quotation, PKS, SPK)

Also read `.github/copilot-instructions.md` (partially overlaps this file).

## Essential Commands

```bash
composer dev    # 4-in-1: server (:8000) + queue:listen + pail logs + Vite HMR
composer test   # config:clear → php artisan test (SQLite :memory:)
php artisan test --filter=TestName   # single test
php artisan test tests/Feature       # feature tests only
php artisan test tests/Unit          # unit tests only
php artisan serve   # dev server on :8000
npm run dev         # Vite HMR (part of composer dev)
npm run build       # prod build
php artisan queue:listen --tries=1  # standalone queue worker
php artisan pail --timeout=0        # real-time logs
```

## Architecture

- **Controllers** → thin, HTTP concerns only. Delegate to **Services**.
- **Services** (`app/Services/`) → business logic, transactions, return DTOs/arrays.
- **DTOs** (`app/DTO/`) → `CalculationSummary`, `DetailCalculation`, `QuotationCalculationResult` — decouple results from models.
- **Resources** (`app/Http/Resources/`) → 3 classes (`QuotationResource`, `QuotationCollection`, `QuotationStepResource`).
- **Requests** (`app/Http/Requests/`) → extend `BaseRequest` (not `FormRequest`) for consistent 422: `{ "message": { "field": ["error"] } }`.
- **API response**: `{ "success": true, "data": {}, "message": "..." }`.
- All routes in `routes/api.php` — grouped by prefix (`/leads`, `/quotations`, `/pks`, etc.).

## Model Conventions (80+ models, `app/Models/`)

- **SoftDeletes** on every model (`deleted_at`, never permanent delete).
- **Audit columns**: `created_by`, `updated_by`, `deleted_by`.
- **Table prefix**: `sl_*` (e.g. `sl_leads`, `sl_quotation`).
- **Scopes**: local scopes `.active()`, `.byBranch($id)`.

## Auth

- Middleware: `auth:sanctum,web` + custom `token.expiry` (aliased in `bootstrap/app.php`).
- `CheckTokenExpiry` middleware checks Sanctum token `expires_at` — logs warning on expiry.
- Custom token model: `HrisPersonalAccessToken` (set via `Sanctum::usePersonalAccessTokenModel` in `bootstrap/app.php`).

## Critical Quirks

### LeadsKebutuhan Pivot
- Use `updateOrCreate`, NOT `firstOrCreate` with composite keys (causes duplicates).
- Relationship must filter soft deletes: `->wherePivot('deleted_at', null)`.
- See `app/Models/Leads.php:78-88` for correct pattern.

### `assignSales()` Known Fixes (LeadsController)
1. Pre-load Kebutuhan with `whereIn()` before loop (N+1 fix).
2. `updateOrCreate` with minimal unique key (duplicates fix).
3. Filter `deleted_at` in `leadsKebutuhan()` relationship.
4. Collect all sales names before single activity log entry.

### Quotation Multi-Site
- `$request->jumlah_site == "Single Site"` — exact case-sensitive string match.
- Site array fields default to `[]` when empty.

## CI/CD (GitLab — `.gitlab-ci.yml`)

```
test → deploy-dev (development branch) → deploy-prod (prod branch)
```
- Test runs in Docker `php:8.2-cli` with `composer install && php artisan test`.
- Deploy via SSH + docker compose, runs `migrate --force`, `l5-swagger:generate`, `optimize:clear`.

## Docker

```bash
docker-compose -f docker-compose-dev.yml up
# Services: cais-v2-be-dev (PHP-FPM) + cais-v2-queue-worker-dev
# Network: shelter-network (external, must exist)
```

## Testing (`phpunit.xml`)

- SQLite `:memory:`, cache/session/queue `array`/`sync`, mail `array`.
- `composer test` clears config first.

## Key Files

| Path | Purpose |
|------|---------|
| `app/Services/` | Business logic — start here for feature changes |
| `app/Http/Controllers/` | Thin controllers |
| `app/Http/Requests/` | Validation (extend `BaseRequest`) |
| `app/Http/Resources/` | API transformers (3 classes) |
| `app/Models/` | 80+ Eloquent models |
| `routes/api.php` | All API routes (auth + resource routes) |
| `bootstrap/app.php` | Middleware registration, custom token model |
| `.gitlab-ci.yml` | CI/CD pipeline (test → deploy) |

# AGENTS.md

> CAIS Backend: Laravel 12 / PHP ^8.2 API for Sales, HR master data, Leads, Quotations, PKS, SPK.

## Source Of Truth

- Trust executable config over prose: `composer.json`, `package.json`, `phpunit.xml`, `.gitlab-ci.yml`, `bootstrap/app.php`, `routes/api.php`.
- `.github/copilot-instructions.md` exists but contains stale route names and response-shape examples; verify before copying from it.
- `.opencode/PRD.md` and `docs/*fulfillment*` are draft product docs for fulfillment/visit work, not current runtime wiring.

## Commands

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link

composer dev                         # serve :8000 + queue:listen --tries=1 + pail + Vite
composer test                        # php artisan config:clear, then php artisan test
php artisan test --filter=TestName   # focused test
php artisan test tests/Feature       # feature tests
php artisan test tests/Unit          # unit tests
npm run build                        # Vite production build
php artisan l5-swagger:generate      # regenerate API docs from @OA annotations
vendor/bin/pint                      # PHP formatting; no composer lint script exists
```

- There is no `npm test`, typecheck, or lint script in `package.json`; do not invent one for verification.
- `.editorconfig` requires LF, 4-space indentation, and 2-space YAML indentation.

## App Wiring

- All API routes are in `routes/api.php`; protected routes use `auth:sanctum,web` plus `token.expiry`.
- Public API routes are only `POST /api/auth/login`, `POST /api/auth/refresh`, and two admin-panel consultation routes before the auth group.
- `bootstrap/app.php` replaces the API middleware group with session middleware plus `ApiResponseMiddleware`; that middleware only forces `Accept: application/json`.
- Route prefixes are plural where defined, for example `quotations` and `quotations-step`, not `/quotation`.
- Controllers/resources build JSON responses directly. Keep the existing endpoint shape; do not assume a global wrapper.

## Validation And Responses

- New request classes should extend `app/Http/Requests/BaseRequest`, which wraps Laravel `FormRequest` and returns 422 as `{ "message": { "field": ["error"] } }`.
- Requests use `sandermuller/laravel-fluent-validation` (`FluentRule`) in current quotation/auth/lead/upah requests.
- Some legacy controller-level validators return `{ "success": false, "message": "...", "errors": ... }`; match local controller behavior when editing nearby code.

## Database And Models

- Default `.env.example` uses SQLite, but many real models/validators reference MySQL connections: `mysql` and `mysqlhris` in `config/database.php`.
- HRIS models such as `User`, `Branch`, `Company`, city/province tables use `mysqlhris`; Sanctum access tokens and refresh tokens use `mysql`.
- PHPUnit forces SQLite `:memory:`, cache/session `array`, queue `sync`, mail `array`; tests touching `mysql`/`mysqlhris` models need explicit connection handling or fakes.
- Domain tables are mostly `sl_*`; master tables are often `m_*`; audit columns are common (`created_by`, `updated_by`, `deleted_by`, and newer `created_by_user_id`) but not universal.
- Many domain models use SoftDeletes, but not every model does. Check the model/migration before assuming `deleted_at` exists; avoid `forceDelete()` for domain records unless explicitly needed.

## Auth

- `User` is `mysqlhris.m_user`, uses Sanctum tokens, and checks passwords with `md5('SHELTER-' . $password . '-SHELTER')` in `scopeCheckLogin()`.
- `User::createTokenPair()` creates a 1-day access token and 7-day refresh token; trust this over stale comments in token models.
- `HrisPersonalAccessToken` is registered in `bootstrap/app.php` via `Sanctum::usePersonalAccessTokenModel()` and hard-codes `$connection = 'mysql'`.

## Quotation Notes

- Start quotation changes in `app/Services/QuotationService.php`, `QuotationBusinessService.php`, `QuotationStepService.php`, and DTOs in `app/DTO/`; controllers still contain some legacy flow glue.
- `QuotationService::calculateQuotation()` returns `QuotationCalculationResult` and preloads HPP/COSS/sites/items into dynamic `_...` properties; see `docs/architecture-overview.md` before changing calculation order.
- `jumlah_site` is an exact string enum: `Single Site` or `Multi Site`. `QuotationStoreRequest` nulls multi-site arrays for single-site requests and defaults them to `[]` for multi-site requests.
- Multi-site arrays `multisite`, `provinsi_multi`, `kota_multi`, and `penempatan_multi` must have matching counts; `QuotationBusinessService::validateMultiSiteData()` enforces this.
- Management-fee component flags live in `sl_quotation_management_fee`; use `QuotationManagementFee::upsertForQuotation()` so soft-deleted rows are restored and unique `quotation_id` conflicts are avoided.

## Leads Notes

- `Leads::kebutuhan()` filters the `sl_leads_kebutuhan` pivot with `wherePivot('deleted_at', null)`; `Leads::leadsKebutuhan()` also filters soft-deleted rows.
- In `LeadsController::assignSales()`, keep the current `updateOrCreate()` pattern and placeholder cleanup; changing to `firstOrCreate()` can recreate duplicate soft-deleted pivot rows.
- Lead and quotation list searches use MySQL fulltext `MATCH ... AGAINST` for `nama_perusahaan`; this will not behave on SQLite without adjustment.

## Docker And CI

- `docker-compose-dev.yml` is deployment-host-specific: absolute `/home/data/development/project-cais-backend` paths, PHP-FPM on port 9000, and external `shelter-network`.
- GitLab CI tests only on `development` and `prod` branch rules, using `php:8.2-cli`, installing `gd`, `pdo_mysql`, `zip`, and `mongodb`, then `cp .env.example .env`, `composer install`, `key:generate`, `config:clear`, `php artisan test`.
- Deploy jobs reset the remote branch, rebuild the backend and queue-worker services, then run `storage:link`, `migrate --force`, `l5-swagger:generate`, and `optimize:clear` inside the container.

# AGENTS.md

> CAIS Backend: Laravel 12 / PHP 8.2 API for sales, HR master data, leads, quotations, PKS, and SPK.

## Source Of Truth

- Trust executable config first: `composer.json`, `package.json`, `phpunit.xml`, `.gitlab-ci.yml`, `bootstrap/app.php`, `routes/api.php`, `config/database.php`.
- `README.md` is useful for setup, but some architecture/response-shape statements are too broad. Match the nearby controller/model behavior before copying patterns.

## Commands

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link

composer dev                         # serve + queue:listen --tries=1 + pail + Vite
composer test                        # clears config, then runs php artisan test
php artisan test --filter=TestName   # focused test
php artisan test tests/Feature
php artisan test tests/Unit
npm run build
php artisan l5-swagger:generate
vendor/bin/pint
```

- `package.json` has only `dev` and `build`; there is no JS test, lint, or typecheck script.
- `.editorconfig` enforces LF, 4-space indentation, and 2-space YAML indentation.

## App Wiring

- All API routes live in `routes/api.php`.
- Only public API routes are `POST /auth/login`, `POST /auth/refresh`, and the two `/admin-panel/consultations` routes before the auth group.
- Protected routes use `auth:sanctum,web` plus `token.expiry`.
- `bootstrap/app.php` replaces the API middleware group with session middleware plus `ApiResponseMiddleware`; that middleware only sets `Accept: application/json`.
- Keep endpoint response shapes consistent with the touched controller/resource. There is no single global JSON wrapper.

## Validation And Data

- New request classes should extend `app/Http/Requests/BaseRequest`; failed validation returns `422` as `{ "message": { "field": ["error"] } }`.
- The repo uses `sandermuller/laravel-fluent-validation` (`FluentRule`) in current request classes; follow the local request style.
- Default local config is SQLite, but the app also uses `mysql` and `mysqlhris` connections. HRIS models like `User`, `Branch`, `Company`, `Province`, and `City` use `mysqlhris`; Sanctum and refresh tokens use `mysql`.
- PHPUnit forces `sqlite :memory:` plus `array` cache/session, `sync` queue, and `array` mail. Tests that hit `mysql` or `mysqlhris` models need fakes, alternate setup, or connection-aware assertions.
- Do not assume every model has `SoftDeletes`; verify the model or migration first.

## Auth

- `App\Models\User` uses `mysqlhris.m_user`.
- Login checks `md5('SHELTER-' . $password . '-SHELTER')` in `User::scopeCheckLogin()`.
- `User::createTokenPair()` creates a 1-day access token and a 7-day refresh token.
- `Sanctum::usePersonalAccessTokenModel()` points to `HrisPersonalAccessToken`, which hard-codes `$connection = 'mysql'`.

## Quotation And Leads Gotchas

- For quotation work, start from `app/Services/QuotationService.php`, `QuotationBusinessService.php`, `QuotationStepService.php`, and the DTOs. Controllers still contain some legacy flow glue.
- `QuotationService::calculateQuotation()` returns `QuotationCalculationResult` and preloads calculation state into dynamic `_...` properties on the quotation model; be careful changing calculation order or load assumptions.
- `jumlah_site` is an exact enum string: `Single Site` or `Multi Site`.
- `QuotationStoreRequest` nulls multi-site arrays for single-site requests and defaults them to `[]` for multi-site requests. `QuotationBusinessService::validateMultiSiteData()` requires `multisite`, `provinsi_multi`, `kota_multi`, and `penempatan_multi` counts to match.
- For quotation management-fee flags, use `QuotationManagementFee::upsertForQuotation()` so soft-deleted rows are restored instead of colliding on the unique `quotation_id`.
- `Leads::kebutuhan()` and `Leads::leadsKebutuhan()` already exclude soft-deleted pivot rows.
- In `LeadsController::assignSales()`, keep the `LeadsKebutuhan::updateOrCreate(...)` plus placeholder-row cleanup. Replacing it with `firstOrCreate()` risks duplicate soft-deleted pivot rows.
- Lead, quotation, customer, PKS, and SPK list searches use MySQL fulltext `MATCH ... AGAINST`; SQLite tests will not exercise that behavior faithfully.

## CI And Docker

- GitLab CI only runs tests on `development` and `prod` branches.
- CI uses `php:8.2-cli`, installs `gd`, `pdo_mysql`, `zip`, and `mongodb`, then runs `cp .env.example .env`, `composer install`, `php artisan key:generate`, `php artisan config:clear`, and `php artisan test`.
- Deployment rebuilds the backend and queue-worker containers, then runs `storage:link`, `migrate --force`, `l5-swagger:generate`, and `optimize:clear` inside the container.
- `docker-compose-dev.yml` is deployment-host-specific, with absolute `/home/data/development/project-cais-backend` paths and external network `shelter-network`; do not assume it is a portable local dev setup.

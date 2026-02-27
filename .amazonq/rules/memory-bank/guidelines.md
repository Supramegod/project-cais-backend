# Development Guidelines

## Code Quality Standards

### File Organization

- **Namespace Structure**: Follow PSR-4 autoloading standard with `App\` as root namespace
    - Controllers: `App\Http\Controllers`
    - Models: `App\Models`
    - Services: `App\Services`
    - DTOs: `App\DTO`
    - Traits: `App\Traits`
    - Events: `App\Events`
    - Listeners: `App\Listeners`

### Code Formatting

- **PHP Standards**: Follow Laravel conventions and PSR-12 coding style
- **Indentation**: Use 4 spaces for PHP, 2 spaces for JavaScript
- **Line Length**: Keep lines reasonable, break long method chains
- **Braces**: Opening braces on same line for methods and classes

### Naming Conventions

- **Classes**: PascalCase (e.g., `QuotationController`, `PksPerjanjianTemplateService`)
- **Methods**: camelCase (e.g., `generateAllSections`, `insertAgreementSections`)
- **Variables**: camelCase (e.g., `$quotationService`, `$currentDateTime`)
- **Constants**: UPPER_SNAKE_CASE
- **Database Tables**: snake_case plural (e.g., `quotations`, `sl_pks_perjanjian`)
- **Database Columns**: snake_case (e.g., `created_at`, `pks_id`)

### Documentation Standards

- **PHPDoc Blocks**: Use for all public methods with description
- **Inline Comments**: Explain complex business logic, not obvious code
- **API Documentation**: Use OpenAPI/Swagger annotations in controllers
- **Method Documentation**: Include `@param`, `@return`, `@throws` tags when applicable

## Architectural Patterns

### Service Layer Pattern

Services contain business logic separated from controllers:

```php
// Service handles business logic
class PksPerjanjianTemplateService
{
    private $leads;
    private $company;
    private $kebutuhan;

    public function __construct(
        Leads $leads,
        Company $company,
        Kebutuhan $kebutuhan,
        RuleThr $ruleThr,
        SalaryRule $salaryRule,
        string $pksNomor
    ) {
        $this->leads = $leads;
        $this->company = $company;
        // ... initialize dependencies
    }

    public function generateAllSections()
    {
        // Complex business logic here
    }
}
```

**Key Principles**:

- Services receive dependencies via constructor injection
- Services return data structures (arrays, DTOs, collections)
- Controllers delegate to services, handle HTTP concerns only
- Services are reusable across multiple controllers

### Data Transfer Objects (DTOs)

Use DTOs for complex data structures:

```php
namespace App\DTO;

class DetailCalculation
{
    public $detail_id;
    public $hpp_data = [];
    public $coss_data = [];

    public function __construct($detail_id)
    {
        $this->detail_id = $detail_id;
    }
}
```

**When to Use DTOs**:

- Transferring complex calculation results
- Grouping related data for API responses
- Encapsulating multi-step process data
- Type-safe data containers

### Controller Patterns

Controllers handle HTTP requests and delegate to services:

```php
public function index(Request $request): JsonResponse
{
    try {
        $query = Quotation::select([...])
            ->with([...])
            ->byUserRole()
            ->dateRange($request->tgl_dari, $request->tgl_sampai)
            ->byCompany($request->company)
            ->notDeleted()
            ->orderBy('created_at', 'desc');

        $data = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $transformedData,
            'pagination' => [...],
            'message' => 'Quotations retrieved successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve quotations',
            'error' => $e->getMessage()
        ], 500);
    }
}
```

**Controller Best Practices**:

- Always return `JsonResponse` for API endpoints
- Wrap logic in try-catch blocks
- Use consistent response structure: `success`, `data`, `message`
- Include pagination metadata when applicable
- Return appropriate HTTP status codes (200, 201, 400, 404, 500)

### Eloquent Model Patterns

Models use query scopes for reusable filters:

```php
// In Model
public function scopeByUserRole($query)
{
    // Filter based on user role
}

public function scopeDateRange($query, $startDate, $endDate)
{
    if ($startDate && $endDate) {
        return $query->whereBetween('tgl_quotation', [$startDate, $endDate]);
    }
}

public function scopeNotDeleted($query)
{
    return $query->whereNull('deleted_at');
}
```

**Model Conventions**:

- Define relationships using Eloquent methods
- Use query scopes for reusable filters (prefix with `scope`)
- Implement soft deletes where appropriate
- Define fillable or guarded properties
- Use accessors/mutators for data transformation

## API Development Standards

### OpenAPI/Swagger Documentation

All API endpoints must have Swagger annotations:

```php
/**
 * @OA\Get(
 *     path="/api/quotations/list",
 *     tags={"Quotations"},
 *     summary="Get all quotations (Optimized)",
 *     description="Retrieves a list of quotations with minimal data for list view",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="tgl_dari",
 *         in="query",
 *         description="Start date filter (YYYY-MM-DD)",
 *         required=false,
 *         @OA\Schema(type="string", format="date", example="2024-01-01")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Quotations retrieved successfully",
 *         @OA\JsonContent(...)
 *     )
 * )
 */
```

**Documentation Requirements**:

- Include path, tags, summary, description
- Document all parameters with types and examples
- Document all response codes (200, 400, 404, 500)
- Include security requirements (bearerAuth)
- Provide example request/response payloads

### API Response Format

Standardized JSON response structure:

```php
// Success Response
{
    "success": true,
    "data": [...],
    "pagination": {
        "current_page": 1,
        "last_page": 5,
        "total": 100,
        "total_per_page": 20
    },
    "message": "Operation successful"
}

// Error Response
{
    "success": false,
    "message": "Operation failed",
    "error": "Detailed error message"
}
```

### Route Organization

Routes organized by resource with consistent naming:

```php
Route::prefix('quotations')->controller(QuotationController::class)->group(function () {
    Route::get('/list', 'index');
    Route::post('/add/{tipe_quotation}', 'store');
    Route::get('/view/{id}', 'show');
    Route::delete('/delete/{id}', 'destroy');
    Route::post('/{id}/submit-approval', 'submitForApproval');
});
```

**Route Conventions**:

- Use resource prefixes (`/quotations`, `/leads`, `/pks`)
- Group related routes with `prefix()` and `controller()`
- Use RESTful naming: `index`, `store`, `show`, `update`, `destroy`
- Add descriptive action names for non-CRUD operations
- Apply middleware at group level: `middleware(['auth:sanctum', 'token.expiry'])`

## Database Patterns

### Migration Standards

- Use descriptive migration names with timestamps
- Include indexes for foreign keys and frequently queried columns
- Use appropriate column types (string, integer, decimal, text, json)
- Define foreign key constraints where applicable
- Include `created_at`, `updated_at`, `created_by`, `updated_by` columns

### Query Optimization

- Use `select()` to specify only needed columns
- Eager load relationships with `with()` to avoid N+1 queries
- Apply filters before pagination
- Use query scopes for complex, reusable filters
- Index columns used in WHERE, JOIN, ORDER BY clauses

### Relationship Definitions

```php
// In Quotation model
public function quotationSites()
{
    return $this->hasMany(QuotationSite::class);
}

public function statusQuotation()
{
    return $this->belongsTo(StatusQuotation::class, 'status_quotation_id');
}
```

## Frontend Integration

### Axios Configuration

Global Axios setup in `bootstrap.js`:

```javascript
import axios from "axios";
window.axios = axios;

window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
```

**API Request Pattern**:

- Set Authorization header: `Bearer {token}`
- Handle responses in `.then()` and errors in `.catch()`
- Display user-friendly error messages
- Show loading states during requests

## Security Best Practices

### Authentication & Authorization

- Use Laravel Sanctum for API token authentication
- Apply `auth:sanctum` middleware to protected routes
- Implement custom `token.expiry` middleware for token validation
- Filter data by user role using query scopes (e.g., `byUserRole()`)

### Input Validation

- Validate all request inputs using Laravel validation rules
- Sanitize user inputs to prevent XSS attacks
- Use parameterized queries (Eloquent ORM) to prevent SQL injection
- Validate file uploads (type, size, extension)

### Data Protection

- Never expose sensitive data in API responses (passwords, tokens)
- Use HTTPS in production
- Implement rate limiting on API endpoints
- Log security-relevant events (failed logins, unauthorized access)

## Testing Standards

### Test Organization

- Feature tests in `tests/Feature/`
- Unit tests in `tests/Unit/`
- Use descriptive test method names: `test_user_can_create_quotation()`

### Testing Best Practices

- Test happy paths and edge cases
- Use factories for test data generation
- Mock external dependencies
- Assert expected responses and database states
- Clean up test data after each test

## Code Review Checklist

### Before Committing

- [ ] Code follows Laravel conventions and PSR-12 standards
- [ ] All methods have appropriate documentation
- [ ] API endpoints have Swagger annotations
- [ ] No sensitive data (credentials, tokens) in code
- [ ] Error handling implemented with try-catch blocks
- [ ] Database queries optimized (eager loading, select specific columns)
- [ ] Input validation implemented
- [ ] Consistent response format used
- [ ] Code formatted with Laravel Pint

### Performance Considerations

- Avoid N+1 query problems with eager loading
- Use pagination for large datasets
- Cache frequently accessed data
- Optimize database indexes
- Use queue jobs for time-consuming tasks
- Minimize API response payload size

## Common Patterns & Idioms

### Dependency Injection

Always inject dependencies via constructor:

```php
public function __construct(
    QuotationService $quotationService,
    QuotationBusinessService $quotationBusinessService
) {
    $this->quotationService = $quotationService;
    $this->quotationBusinessService = $quotationBusinessService;
}
```

### Collection Transformation

Use Laravel collections for data transformation:

```php
$transformedData = $data->getCollection()->transform(function ($quotation) {
    return [
        'id' => $quotation->id,
        'nomor' => $quotation->nomor,
        'status_quotation' => $quotation->statusQuotation ? [
            'id' => $quotation->statusQuotation->id,
            'nama' => $quotation->statusQuotation->nama
        ] : null,
    ];
});
```

### Carbon for Date Handling

Use Carbon for date manipulation:

```php
use Illuminate\Support\Carbon;

$tanggalSekarang = Carbon::now()->locale('id')->isoFormat('dddd, D MMMM Y');
$this->currentDateTime = Carbon::now();
```

### Database Transactions

Wrap multi-step database operations in transactions:

```php
\DB::transaction(function () {
    // Multiple database operations
    $quotation->save();
    $quotation->sites()->createMany($sites);
});
```

### Event-Driven Actions

Use events and listeners for decoupled actions:

```php
// Fire event
event(new QuotationCreated($quotation));

// Listener handles async processing
class ProcessQuotationDuplication
{
    public function handle(QuotationCreated $event)
    {
        // Process duplication in background
    }
}
```

## Environment Configuration

### Required Environment Variables

```
APP_NAME=Laravel
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=sqlite
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

### Development Workflow

1. Run migrations: `php artisan migrate`
2. Generate API docs: `php artisan l5-swagger:generate`
3. Start dev server: `composer dev` (runs server, queue, logs, vite)
4. Format code: `./vendor/bin/pint`
5. Run tests: `php artisan test`

## Deployment Checklist

- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Generate application key: `php artisan key:generate`
- [ ] Run migrations: `php artisan migrate --force`
- [ ] Optimize autoloader: `composer install --optimize-autoloader --no-dev`
- [ ] Cache configuration: `php artisan config:cache`
- [ ] Cache routes: `php artisan route:cache`
- [ ] Build frontend assets: `npm run build`
- [ ] Set up queue workers as background processes
- [ ] Configure log rotation
- [ ] Enable HTTPS and set proper CORS headers
- [ ] Set up database backups

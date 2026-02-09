# Project Structure

## Directory Organization

### `/app` - Application Core
Main application logic following Laravel conventions.

#### `/app/Http/Controllers`
API controllers handling HTTP requests:
- **AuthController**: Authentication (login, logout, refresh, user info)
- **LeadsController**: Lead management and tracking
- **QuotationController**: Quotation CRUD and lifecycle
- **QuotationStepController**: Multi-step quotation workflow
- **PksController**: Partnership agreement management
- **SpkController**: Work order management
- **CustomerController**: Customer data access
- **CustomerActivityController**: Customer interaction logging
- **SalesActivityController**: Sales visit and activity tracking
- **SalesRevenueController**: Revenue reporting and analytics
- **TargetController**: Sales target management
- **TimSalesController**: Sales team management
- **OptionController**: Master data options/dropdowns
- **AdminPanelController**: Administrative quotation updates
- **DashboardApprovalController**: Approval workflow dashboard

Master data controllers:
- Position, Kebutuhan (services), Training, Supplier
- Barang (items), JenisBarang (item types)
- ManagementFee, Top (payment terms), SalaryRule
- Tunjangan (allowances), UMP/UMK (regional wages)
- Kaporlap, Devices, Chemical, OHC

#### `/app/Models`
Eloquent ORM models representing database tables:
- **Core Business**: Leads, Customer, Quotation, Pks, Spk, Site
- **Quotation Details**: QuotationDetail, QuotationSite, QuotationPic, QuotationMargin
- **Quotation Components**: QuotationDetailHpp, QuotationDetailWage, QuotationDetailTunjangan, QuotationDetailRequirement, QuotationDetailCoss
- **Quotation Resources**: QuotationKaporlap, QuotationDevices, QuotationChemical, QuotationOhc, QuotationAplikasi, QuotationTraining
- **Master Data**: Position, Kebutuhan, Training, Barang, Supplier, Company, Branch
- **Configuration**: ManagementFee, Top, SalaryRule, TunjanganPosisi, Ump, Umk
- **Location**: Province, City, District, Village, Negara, Benua
- **Status**: StatusLeads, StatusQuotation, StatusPks, StatusSpk
- **User Management**: User, Role, Sysmenu, SysmenuRole
- **Activity Tracking**: CustomerActivity, SalesActivity, LogApproval, LogNotification

#### `/app/Services`
Business logic services:
- **QuotationService**: Core quotation operations
- **QuotationBusinessService**: Business rules and calculations
- **QuotationStepService**: Step-by-step workflow management
- **QuotationDuplicationService**: Copy quotations between sites
- **QuotationBarangService**: Item/equipment management
- **RekontrakService**: Contract renewal logic
- **PksPerjanjianTemplateService**: Contract template generation
- **SalesRevenueService**: Revenue calculation and reporting
- **DynamicMailerService**: Email sending with user configs
- **DocumentCompressionService**: File compression utilities

#### `/app/DTO`
Data Transfer Objects for complex data structures:
- **QuotationCalculationResult**: Complete calculation output
- **CalculationSummary**: Summary of cost calculations
- **DetailCalculation**: Detailed cost breakdown

#### `/app/Traits`
Reusable traits for common functionality:
- **ApiResponser**: Standardized API response formatting
- **FilterSortTrait**: Query filtering and sorting
- **PaginationTrait**: Pagination helpers
- **SearchTrait**: Search functionality

#### `/app/Events` & `/app/Listeners`
Event-driven architecture:
- **QuotationCreated** event → **ProcessQuotationDuplication** listener

#### `/app/Mail`
Email templates:
- **CustomerActivityEmail**: Customer activity notifications

#### `/app/Rules`
Custom validation rules:
- **UniqueCompanyStrict**: Company uniqueness validation

### `/routes`
API routing definitions:
- **api.php**: All API endpoints with Sanctum authentication
- **web.php**: Web routes (minimal, API-focused)
- **console.php**: Artisan console commands

### `/config`
Laravel configuration files:
- **database.php**: Database connections (SQLite default)
- **auth.php**: Authentication configuration
- **sanctum.php**: API token settings
- **l5-swagger.php**: Swagger/OpenAPI documentation
- **mail.php**: Email configuration
- **queue.php**: Queue configuration (database driver)

### `/database`
Database layer:
- **/migrations**: Schema migrations for table creation
- **/seeders**: Database seeders
- **/factories**: Model factories for testing
- **database.sqlite**: SQLite database file

### `/storage`
File storage:
- **/api-docs**: Generated Swagger documentation (api-docs.json)
- **/app/private**: Private file storage
- **/app/public**: Public file storage
- **/logs**: Application logs (laravel.log)

### `/public`
Public web root:
- **/document**: Uploaded documents
  - **/customer-activity**: Customer activity files
  - **/sales-activity**: Sales activity files
  - **/pks**: PKS contract documents
  - **/spk**: SPK work order documents
- **index.php**: Application entry point

### `/resources`
Frontend assets and views:
- **/js**: JavaScript files (app.js, bootstrap.js)
- **/css**: Stylesheets (app.css)
- **/views**: Blade templates (minimal, API-focused)

### `/tests`
Test suites:
- **/Feature**: Feature tests
- **/Unit**: Unit tests

## Core Components & Relationships

### Authentication Flow
```
User → AuthController → Sanctum Token → Middleware (auth:sanctum, token.expiry)
```

### Quotation Workflow
```
Leads → Quotation (12 steps) → Approval → SPK/PKS → Contract Activation
```

### Data Hierarchy
```
Leads (Customer)
  └── LeadsKebutuhan (Service Requirements)
  └── Quotation
      ├── QuotationSite (Multiple sites)
      ├── QuotationDetail (Positions per site)
      │   ├── QuotationDetailHpp (Cost of goods)
      │   ├── QuotationDetailWage (Wages)
      │   ├── QuotationDetailTunjangan (Allowances)
      │   └── QuotationDetailRequirement (Requirements)
      ├── QuotationKaporlap (Security equipment)
      ├── QuotationDevices (Devices)
      ├── QuotationChemical (Chemicals)
      └── QuotationOhc (Overhead costs)
  └── SPK (Work Order)
      └── SpkSite (Sites covered)
  └── PKS (Partnership Agreement)
```

### Service Layer Pattern
Controllers delegate business logic to Services:
- Controllers handle HTTP concerns (validation, responses)
- Services contain business rules and calculations
- Models handle database interactions
- DTOs transfer complex data between layers

## Architectural Patterns

### RESTful API Design
- Resource-based endpoints (`/api/quotations`, `/api/leads`)
- HTTP verbs for operations (GET, POST, PUT, DELETE)
- JSON request/response format
- Standardized error handling

### Repository Pattern (via Eloquent)
- Models act as repositories
- Query scopes for reusable filters (byUserRole, dateRange, byCompany)
- Relationships defined in models

### Service Layer Pattern
- Business logic separated from controllers
- Reusable services across multiple controllers
- Dependency injection for testability

### Event-Driven Architecture
- Events for significant business actions
- Listeners for asynchronous processing
- Queue support for background jobs

### Multi-Tenancy Support
- Company/entity filtering in queries
- User role-based data access
- Branch-based data segregation

### API Documentation
- OpenAPI/Swagger annotations in controllers
- Auto-generated documentation at `/api/documentation`
- Comprehensive request/response examples

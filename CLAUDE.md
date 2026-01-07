# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Point of Sale (POS) system built with Laravel 12 and Vue 3 using Inertia.js. The application handles:
- Sales order management
- Product and inventory tracking
- Customer management
- MyInvois e-invoice integration (Malaysian e-invoicing system)
- Quotation management
- TMS receipt integration for 1Utama

## Tech Stack

**Backend:**
- Laravel 12 (PHP 8.2+)
- SQLite database (default)
- Queue system (database driver)
- Spatie Permissions for role-based access control
- Laravel Auditing for activity tracking
- DOMPDF for PDF generation

**Frontend:**
- Vue 3 with TypeScript
- Inertia.js for SPA routing
- Tailwind CSS 4
- Reka UI components
- Pinia for state management
- Chart.js for analytics

**Key Dependencies:**
- `klsheng/myinvois-php-sdk` - MyInvois integration
- `phpoffice/phpspreadsheet` - Excel exports
- `spatie/laravel-backup` - Database backups

## Development Commands

### Start Development Environment
```bash
# Full dev environment (server, queue worker, logs, vite)
composer dev
```
This runs concurrently:
- Laravel dev server (port 8000)
- Queue worker
- Log viewer (Laravel Pail)
- Vite dev server

### Individual Services
```bash
# Start Laravel server only
php artisan serve

# Run queue worker
php artisan queue:listen --tries=1

# Watch logs
php artisan pail --timeout=0

# Start Vite dev server
npm run dev
```

### Build & Assets
```bash
# Build frontend assets
npm run build

# Build with SSR support
npm run build:ssr

# Run dev with SSR
composer dev:ssr
```

### Code Quality
```bash
# Format code
npm run format

# Check formatting
npm run format:check

# Lint and fix
npm run lint

# PHP code style (Laravel Pint)
./vendor/bin/pint
```

### Testing
```bash
# Run all tests (Pest)
php artisan test

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Run with coverage
php artisan test --coverage
```

### Database
```bash
# Run migrations
php artisan migrate

# Fresh migration with seeders
php artisan migrate:fresh --seed

# Create new migration
php artisan make:migration create_table_name
```

### Other Commands
```bash
# Create database backup
php artisan backup:run

# Download SQL backup
# Visit /backup/sql (authenticated route)

# Check MyInvois queue and push pending invoices
php artisan myinvois:push-queue

# Check daily sales (scheduled command)
php artisan check:daily-sales
```

## Architecture & Key Concepts

### Route Organization
Routes are split across multiple files:
- `routes/web.php` - Main application routes (orders, reports, invoices)
- `routes/pos.php` - POS-specific routes (products, categories, customers)
- `routes/settings.php` - User profile and settings routes
- `routes/auth.php` - Authentication routes

### Controllers
Controllers follow resource patterns where applicable:
- `OrderController` - Main sales order management, MyInvois submission
- `ProductController` - Product CRUD, stock adjustments, inventory cost reports
- `InvoiceController` - PDF invoice generation and email sending
- `CustomerController` - Customer management with search
- `QuoteController` - Quotation management and PDF generation

### Models & Relationships
Key models and their relationships:
- `Order` - hasMany `OrderItem`, belongsTo `Customer`, hasOne `MyInvoisInvoice`, hasOne `MyInvoisQueue`
- `Product` - hasMany `OrderItem`, belongsTo `Category`, belongsTo `Supplier`
- `Customer` - hasMany `Order`
- `MyInvoisInvoice` - belongsTo `Order`, tracks submitted e-invoices
- `MyInvoisQueue` - belongsTo `Order`, tracks pending e-invoice submissions

All models use soft deletes. `Order` model implements Laravel Auditing for tracking changes.

### Services
`MyInvoisService` - Handles all MyInvois API interactions:
- Invoice submission to external e-invoicing system
- Phone number formatting (must match MyInvois regex: `^\+[1-9]\d{1,14}$`)
- Customer data validation
- E-invoice PDF generation and email delivery

Configuration in `config/services.php`:
```php
'myinvois' => [
    'enabled' => env('MYINVOIS_ENABLED', false),
    'base_url' => env('MYINVOIS_BASE_URL'),
    'queue_delay_hours' => env('MYINVOIS_QUEUE_DELAY_HOURS', 72),
    'einvoice_claim_url' => env('EINVOICE_CLAIM_URL'),
    'branch' => env('EINVOICE_BRANCH'),
]
```

### Frontend Structure
- `resources/js/pages/` - Inertia page components organized by feature
- `resources/js/Components/` - Reusable Vue components
- `resources/js/Components/ui/` - UI component library (buttons, inputs, modals, etc.)
- `resources/js/Layouts/` - Layout components
- `resources/js/types/` - TypeScript type definitions
- `resources/js/composables/` - Vue composables for shared logic

Path alias: `@/` maps to `resources/js/`

### Helper Functions
Global helper in `app/helpers.php`:
```php
settings($key, $default = null) // Access shop settings
```

### Queue System
The application uses database-driven queues for:
- MyInvois invoice submissions (delayed by configured hours)
- Email sending

Queue worker should always be running in development/production.

### API Endpoints
External API endpoint for MyInvois submission:
```
POST /api/orders/{orderId}/submit-myinvois
```
This endpoint:
- Bypasses CSRF protection
- Accepts custom customer information
- Auto-creates/updates customer by phone or email
- Submits invoice to MyInvois
- Sends e-invoice PDF via email

Full documentation in `API_SAMPLE_REQUEST.md`

### Invoice System
Two invoice types:
1. **Regular Invoice** - PDF generated via `InvoiceController::generate()`
2. **MyInvois E-Invoice** - Generated after successful MyInvois submission
   - Accessed via `/orders/{order}/e-invoice`
   - PDF version via `/orders/{order}/e-invoice/pdf`

### Permissions & Roles
Uses Spatie Laravel Permission package. Role model defined in `app/Models/Role.php`.

### Activity Logging
Orders are audited using `owen-it/laravel-auditing`. View logs at `/activity-log`.

## Important Patterns

### Order Status Flow
```
pending → processing → completed
                    ↘ cancelled
```

### MyInvois Integration Flow
1. Order created
2. Added to `MyInvoisQueue` (optional, with delay)
3. Queue processed manually or via `PushMyInvoisQueue` command
4. Invoice submitted to MyInvois service
5. `MyInvoisInvoice` record created
6. E-invoice PDF emailed to customer

### Customer Lookup Logic
When submitting MyInvois invoices:
1. Search by phone number (primary)
2. Fallback to email if no phone
3. Update existing customer if found
4. Create new customer if not found
5. Assign order to customer

### Identification Scheme Priority
For MyInvois submissions:
1. BRN (Business Registration Number) if provided
2. NRIC (National Registration Identity Card) if no BRN
3. Default BRN "000000000000" if neither provided

## Environment Configuration

Key environment variables:
```bash
# Database (default: SQLite)
DB_CONNECTION=sqlite

# Queue & Cache
QUEUE_CONNECTION=database
CACHE_STORE=database

# MyInvois Integration
MYINVOIS_ENABLED=true|false
MYINVOIS_BASE_URL=https://myinvois.example.com
MYINVOIS_QUEUE_DELAY_HOURS=72
EINVOICE_CLAIM_URL=https://einvoice.example.com
EINVOICE_BRANCH=branch_name

# TMS Receipts (1Utama)
TMS_ENDPOINT=https://tms.1utama.com.my/POS/POSService.svc/SendReceipts
TMS_AUTHORIZATION_TOKEN=your_token
TMS_IS_TEST=true|false

# Email
MAIL_MAILER=smtp
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_FROM_ADDRESS=your_email@example.com
```

## Testing Notes

Tests use Pest framework:
- Feature tests in `tests/Feature/`
- Unit tests in `tests/Unit/`

Architecture tests available via `ta-tikoma/phpunit-architecture-test` package.

## Common Gotchas

1. **Phone Numbers**: MyInvois requires international format starting with `+` and first digit 1-9 (e.g., `+60123456789`)
2. **Queue Worker**: Must be running for MyInvois submissions to process
3. **Invoice Numbers**: Auto-formatted as `{YM}-{ID}` (e.g., `2501-123`)
4. **Soft Deletes**: All models use soft deletes; check `deleted_at` when querying
5. **Settings Helper**: Shop settings are cached in the helper function; may need to clear cache after updates
6. **SSR Support**: Inertia SSR is configured but requires separate build step

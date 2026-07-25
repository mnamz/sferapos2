# Sales Register by Product Type Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "Sales Register by Product Type" report that lists sold products grouped by category (quantity + gross sales per product, per-category subtotals, grand total), with filters, an Excel export, and a combined-invoices PDF download.

**Architecture:** New methods on the existing `ReportController` (`salesRegister`, `salesRegisterExport`, `salesRegisterInvoices`) backed by a raw `DB` query joining `order_items → orders → products → categories`. A new Inertia Vue page `Reports/SalesRegister.vue` renders the grouped tables. The invoices bundle reuses the existing `pdf.invoice` markup by extracting its body into a shared Blade partial. A sidebar link makes it reachable.

**Tech Stack:** Laravel 12, Inertia.js, Vue 3 + TypeScript, Tailwind, PhpSpreadsheet (Excel), barryvdh/laravel-dompdf (PDF), Pest (tests).

## Global Constraints

- Exclude non-sales orders everywhere: `orders.status != 'cancelled'` AND `orders.deleted_at IS NULL` (soft deletes).
- Date range filters `orders.created_at` between `start_date 00:00:00` and `end_date 23:59:59`, inclusive. Defaults: start = first day of current month, end = today.
- "Sales" per product = `SUM(order_items.total)` (gross line total, price × qty). Delivery/tax/order-discount excluded.
- "Brand" = first whitespace-delimited word of `order_items.product_name` (case-insensitive), matched as `product_name LIKE '<brand> %' OR product_name = '<brand>'`.
- Category grouping key: `COALESCE(categories.name, 'Uncategorized')`.
- Money displayed to 2 decimals; quantities as integers.
- Route names: `reports.sales-register`, `reports.sales-register.export`, `reports.sales-register.invoices`. All under the existing `['auth','verified']` group in `routes/web.php`.
- Follow existing code style (Pint for PHP). Run `./vendor/bin/pint` before committing PHP.

---

## File Structure

- **Modify** `routes/web.php` — register 3 routes.
- **Modify** `app/Http/Controllers/ReportController.php` — add `salesRegister`, `salesRegisterExport`, `salesRegisterInvoices`, plus private helpers `salesRegisterBaseQuery`, `salesRegisterFilterOptions`, `salesRegisterMatchingOrders`.
- **Create** `resources/js/pages/Reports/SalesRegister.vue` — report page.
- **Modify** `resources/js/Components/AppSidebar.vue` — add "Sales Register" nav item + role filter.
- **Create** `resources/views/pdf/partials/invoice-body.blade.php` — extracted invoice body (shared).
- **Modify** `resources/views/pdf/invoice.blade.php` — include the partial.
- **Create** `resources/views/pdf/invoices-bundle.blade.php` — loops orders, includes the partial with page breaks.
- **Create** `tests/Feature/SalesRegisterReportTest.php` — feature tests.

---

## Task 1: Route + controller stub + empty Vue page + nav link

Get the page reachable and rendering before adding logic.

**Files:**
- Modify: `routes/web.php` (after line 23, the `reports/export` route)
- Modify: `app/Http/Controllers/ReportController.php`
- Create: `resources/js/pages/Reports/SalesRegister.vue`
- Modify: `resources/js/Components/AppSidebar.vue`
- Create: `tests/Feature/SalesRegisterReportTest.php`

**Interfaces:**
- Produces: route `reports.sales-register` → `ReportController@salesRegister`, renders Inertia page `Reports/SalesRegister` with props `groups` (array), `grandTotal` (`{quantity,sales}`), `filterOptions` (object), `filters` (object).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/SalesRegisterReportTest.php`:

```php
<?php

use App\Models\User;

it('renders the sales register report page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.sales-register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/SalesRegister')
            ->has('groups')
            ->has('grandTotal')
            ->has('filterOptions')
            ->has('filters')
        );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/SalesRegisterReportTest.php`
Expected: FAIL — route `reports.sales-register` not defined (RouteNotFoundException).

- [ ] **Step 3: Register the routes**

In `routes/web.php`, immediately after the line:
```php
Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
```
add:
```php
Route::get('reports/sales-register', [ReportController::class, 'salesRegister'])->name('reports.sales-register');
Route::get('reports/sales-register/export', [ReportController::class, 'salesRegisterExport'])->name('reports.sales-register.export');
Route::get('reports/sales-register/invoices', [ReportController::class, 'salesRegisterInvoices'])->name('reports.sales-register.invoices');
```

- [ ] **Step 4: Add the controller stub**

In `app/Http/Controllers/ReportController.php`, add these `use` statements near the top (after existing imports):
```php
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
```
Then add this method to the class (after `export`):
```php
    public function salesRegister(Request $request)
    {
        return Inertia::render('Reports/SalesRegister', [
            'groups' => [],
            'grandTotal' => ['quantity' => 0, 'sales' => 0],
            'filterOptions' => [
                'brands' => [],
                'categories' => [],
                'salespersons' => [],
                'customers' => [],
                'paymentMethods' => [],
                'deliveryMethods' => [],
            ],
            'filters' => [
                'start_date' => $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d')),
                'end_date' => $request->input('end_date', Carbon::now()->format('Y-m-d')),
                'brand' => $request->input('brand'),
                'category_id' => $request->input('category_id'),
                'user_id' => $request->input('user_id'),
                'customer_id' => $request->input('customer_id'),
                'payment_method' => $request->input('payment_method'),
                'delivery_method' => $request->input('delivery_method'),
            ],
        ]);
    }
```

- [ ] **Step 5: Create the Vue page stub**

Create `resources/js/pages/Reports/SalesRegister.vue`:
```vue
<template>
    <Head title="Sales Register" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    Sales Register by Product Type
                </h1>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    groups: Array,
    grandTotal: Object,
    filterOptions: Object,
    filters: Object,
});

const breadcrumbs = [
    { title: 'Sales Register', href: route('reports.sales-register') },
];
</script>
```

- [ ] **Step 6: Add the sidebar nav item**

In `resources/js/Components/AppSidebar.vue`, inside the `mainNavItems` array, add this entry immediately after the `Reports` item (after its closing `},` around line 73):
```js
    {
        title: 'Sales Register',
        href: route('reports.sales-register'),
        icon: BarChart3,
    },
```
Then, in the `filteredNavItems` computed, add this hide rule alongside the existing `Reports` rule (after the block that returns false for `Reports`):
```js
        if (item.title === 'Sales Register' && roles.includes('staff')) {
            return false;
        }
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test tests/Feature/SalesRegisterReportTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
./vendor/bin/pint app/Http/Controllers/ReportController.php
git add routes/web.php app/Http/Controllers/ReportController.php resources/js/pages/Reports/SalesRegister.vue resources/js/Components/AppSidebar.vue tests/Feature/SalesRegisterReportTest.php
git commit -m "feat(reports): scaffold Sales Register report page, route, nav"
```

---

## Task 2: Grouping query + totals + gross-sales + exclusions

Implement the core aggregation so the page returns real grouped data.

**Files:**
- Modify: `app/Http/Controllers/ReportController.php`
- Test: `tests/Feature/SalesRegisterReportTest.php`

**Interfaces:**
- Produces:
  - `private function salesRegisterBaseQuery(Request $request)` → returns a `DB` query builder on `order_items` joined to `orders/products/categories` with global constraints, date range, and all filters applied. Used by both `salesRegister` and export.
  - `salesRegister` now returns real `groups` (array of `['category'=>string,'products'=>[['name','quantity','sales']],'quantity_total'=>int,'sales_total'=>float]`) and `grandTotal` (`['quantity'=>int,'sales'=>float]`).
  - Model factories: `CategoryFactory`, `ProductFactory`, `CustomerFactory`, `OrderFactory` (only `UserFactory` existed before).

> **Note:** Before this task, only `database/factories/UserFactory.php` existed even though `Order`, `Product`, `Customer`, `Category` all use `HasFactory`. Step 1 creates the missing factories. `categories.status` and `products.status` are `enum('active','inactive')` (default `'active'`) — never pass a boolean. `orders` requires `user_id`, `subtotal`, `tax`, `total`, `paid_amount` (no DB defaults); `products` requires `name`, `price`, `category_id`. `created_at` is not mass-assignable, so the helper sets it via `forceFill(...)->save()`.

- [ ] **Step 1: Create the missing model factories**

Create `database/factories/CategoryFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'status' => 'active',
        ];
    }
}
```

Create `database/factories/ProductFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'cost_price' => 0,
            'stock' => 0,
            'category_id' => Category::factory(),
            'status' => 'active',
        ];
    }
}
```

Create `database/factories/CustomerFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
        ];
    }
}
```

Create `database/factories/OrderFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0,
            'paid_amount' => 0,
            'status' => 'completed',
        ];
    }
}
```

- [ ] **Step 2: Write the failing tests**

Add to `tests/Feature/SalesRegisterReportTest.php` (add `use` lines at top: `use App\Models\Category; use App\Models\Order; use App\Models\OrderItem; use App\Models\Product;`):

```php
function makeOrder(array $orderAttrs, array $items): Order
{
    $createdAt = $orderAttrs['created_at'] ?? '2026-06-15 10:00:00';
    unset($orderAttrs['created_at']);

    $order = Order::factory()->create($orderAttrs);
    $order->forceFill(['created_at' => $createdAt])->save();

    foreach ($items as $item) {
        OrderItem::create(array_merge([
            'order_id' => $order->id,
            'quantity' => 1,
            'price' => 0,
            'cost_price' => 0,
            'total' => 0,
            'profit' => 0,
        ], $item));
    }

    return $order->fresh();
}

it('groups products by category with per-product qty and gross sales', function () {
    $user = User::factory()->create();
    $cameras = Category::create(['name' => 'DIGITAL CAMERA']);
    $drone = Product::factory()->create(['name' => 'DJI NEO 2', 'category_id' => $cameras->id]);

    makeOrder(['user_id' => $user->id], [
        ['product_id' => $drone->id, 'product_name' => 'DJI NEO 2', 'quantity' => 2, 'price' => 100, 'total' => 200],
        ['product_id' => $drone->id, 'product_name' => 'DJI NEO 2', 'quantity' => 3, 'price' => 100, 'total' => 300],
    ]);

    $this->actingAs($user)
        ->get(route('reports.sales-register', ['start_date' => '2026-06-01', 'end_date' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->where('groups.0.category', 'DIGITAL CAMERA')
            ->where('groups.0.products.0.name', 'DJI NEO 2')
            ->where('groups.0.products.0.quantity', 5)
            ->where('groups.0.products.0.sales', 500)
            ->where('groups.0.quantity_total', 5)
            ->where('groups.0.sales_total', 500)
            ->where('grandTotal.quantity', 5)
            ->where('grandTotal.sales', 500)
        );
});

it('excludes cancelled and soft-deleted orders from figures', function () {
    $user = User::factory()->create();
    $cat = Category::create(['name' => 'DJI']);
    $p = Product::factory()->create(['name' => 'DJI MINI 4 PRO', 'category_id' => $cat->id]);

    makeOrder(['user_id' => $user->id], [
        ['product_id' => $p->id, 'product_name' => 'DJI MINI 4 PRO', 'quantity' => 1, 'price' => 50, 'total' => 50],
    ]);
    makeOrder(['user_id' => $user->id, 'status' => 'cancelled'], [
        ['product_id' => $p->id, 'product_name' => 'DJI MINI 4 PRO', 'quantity' => 9, 'price' => 50, 'total' => 450],
    ]);
    $deleted = makeOrder(['user_id' => $user->id], [
        ['product_id' => $p->id, 'product_name' => 'DJI MINI 4 PRO', 'quantity' => 7, 'price' => 50, 'total' => 350],
    ]);
    $deleted->delete();

    $this->actingAs($user)
        ->get(route('reports.sales-register', ['start_date' => '2026-06-01', 'end_date' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->where('grandTotal.quantity', 1)
            ->where('grandTotal.sales', 50)
        );
});

it('respects the date range boundaries', function () {
    $user = User::factory()->create();
    $cat = Category::create(['name' => 'DJI']);
    $p = Product::factory()->create(['name' => 'DJI AIR 3', 'category_id' => $cat->id]);

    makeOrder(['user_id' => $user->id, 'created_at' => '2026-05-31 23:59:59'], [
        ['product_id' => $p->id, 'product_name' => 'DJI AIR 3', 'quantity' => 4, 'total' => 400],
    ]);
    makeOrder(['user_id' => $user->id, 'created_at' => '2026-06-01 00:00:00'], [
        ['product_id' => $p->id, 'product_name' => 'DJI AIR 3', 'quantity' => 1, 'total' => 100],
    ]);

    $this->actingAs($user)
        ->get(route('reports.sales-register', ['start_date' => '2026-06-01', 'end_date' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page->where('grandTotal.quantity', 1));
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test tests/Feature/SalesRegisterReportTest.php`
Expected: FAIL — `groups` is empty `[]`, so `groups.0.category` assertions fail.

- [ ] **Step 4: Implement the base query and grouping**

In `app/Http/Controllers/ReportController.php`, add this private helper:
```php
    private function salesRegisterBaseQuery(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($request->input('brand'), function ($query, $brand) {
                $query->where(function ($q) use ($brand) {
                    $q->where('order_items.product_name', 'like', $brand . ' %')
                      ->orWhere('order_items.product_name', $brand);
                });
            })
            ->when($request->input('category_id'), fn ($query, $id) => $query->where('products.category_id', $id))
            ->when($request->input('user_id'), fn ($query, $id) => $query->where('orders.user_id', $id))
            ->when($request->input('customer_id'), fn ($query, $id) => $query->where('orders.customer_id', $id))
            ->when($request->input('payment_method'), fn ($query, $m) => $query->where('orders.payment_method', $m))
            ->when($request->input('delivery_method'), fn ($query, $m) => $query->where('orders.delivery_method', $m));
    }
```

Then replace the body of `salesRegister` (keep the same method signature) with:
```php
    public function salesRegister(Request $request)
    {
        $rows = $this->salesRegisterBaseQuery($request)
            ->select(
                DB::raw("COALESCE(categories.name, 'Uncategorized') as category"),
                'order_items.product_name as product_name',
                DB::raw('SUM(order_items.quantity) as quantity'),
                DB::raw('SUM(order_items.total) as sales')
            )
            ->groupBy('category', 'order_items.product_name')
            ->orderBy('category')
            ->orderBy('order_items.product_name')
            ->get();

        $groups = [];
        $grandQuantity = 0;
        $grandSales = 0.0;

        foreach ($rows as $row) {
            $category = $row->category;
            if (! isset($groups[$category])) {
                $groups[$category] = [
                    'category' => $category,
                    'products' => [],
                    'quantity_total' => 0,
                    'sales_total' => 0.0,
                ];
            }

            $quantity = (int) $row->quantity;
            $sales = (float) $row->sales;

            $groups[$category]['products'][] = [
                'name' => $row->product_name,
                'quantity' => $quantity,
                'sales' => $sales,
            ];
            $groups[$category]['quantity_total'] += $quantity;
            $groups[$category]['sales_total'] += $sales;

            $grandQuantity += $quantity;
            $grandSales += $sales;
        }

        return Inertia::render('Reports/SalesRegister', [
            'groups' => array_values($groups),
            'grandTotal' => ['quantity' => $grandQuantity, 'sales' => $grandSales],
            'filterOptions' => [
                'brands' => [],
                'categories' => [],
                'salespersons' => [],
                'customers' => [],
                'paymentMethods' => [],
                'deliveryMethods' => [],
            ],
            'filters' => [
                'start_date' => $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d')),
                'end_date' => $request->input('end_date', Carbon::now()->format('Y-m-d')),
                'brand' => $request->input('brand'),
                'category_id' => $request->input('category_id'),
                'user_id' => $request->input('user_id'),
                'customer_id' => $request->input('customer_id'),
                'payment_method' => $request->input('payment_method'),
                'delivery_method' => $request->input('delivery_method'),
            ],
        ]);
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/SalesRegisterReportTest.php`
Expected: PASS (all grouping/exclusion/date tests green).

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint app/Http/Controllers/ReportController.php
git add database/factories/CategoryFactory.php database/factories/ProductFactory.php database/factories/CustomerFactory.php database/factories/OrderFactory.php app/Http/Controllers/ReportController.php tests/Feature/SalesRegisterReportTest.php
git commit -m "feat(reports): sales register grouping query with totals and exclusions"
```

---

## Task 3: Filters (brand, category, salesperson, customer, method)

Verify each filter narrows the result set.

**Files:**
- Test: `tests/Feature/SalesRegisterReportTest.php`

**Interfaces:**
- Consumes: `salesRegisterBaseQuery` (already applies all filters from Task 2). This task only adds tests; no production change expected unless a test fails.

- [ ] **Step 1: Write the failing/verification tests**

Add to `tests/Feature/SalesRegisterReportTest.php`:

```php
it('filters by brand on the first word of the product name, case-insensitive', function () {
    $user = User::factory()->create();
    $cat = Category::create(['name' => 'DJI']);
    $dji = Product::factory()->create(['name' => 'DJI MINI 4 PRO', 'category_id' => $cat->id]);
    $insta = Product::factory()->create(['name' => 'INSTA360 X4', 'category_id' => $cat->id]);
    $mini = Product::factory()->create(['name' => 'MINIATURE TRIPOD', 'category_id' => $cat->id]);

    makeOrder(['user_id' => $user->id], [
        ['product_id' => $dji->id, 'product_name' => 'DJI MINI 4 PRO', 'quantity' => 2, 'total' => 200],
        ['product_id' => $insta->id, 'product_name' => 'INSTA360 X4', 'quantity' => 5, 'total' => 500],
        ['product_id' => $mini->id, 'product_name' => 'MINIATURE TRIPOD', 'quantity' => 9, 'total' => 900],
    ]);

    $this->actingAs($user)
        ->get(route('reports.sales-register', [
            'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'brand' => 'dji',
        ]))
        ->assertInertia(fn ($page) => $page->where('grandTotal.quantity', 2));
});

it('filters by category', function () {
    $user = User::factory()->create();
    $cameras = Category::create(['name' => 'DIGITAL CAMERA']);
    $acc = Category::create(['name' => 'ACCESSORIES']);
    $cam = Product::factory()->create(['name' => 'DJI NEO 2', 'category_id' => $cameras->id]);
    $strap = Product::factory()->create(['name' => 'DJI STRAP', 'category_id' => $acc->id]);

    makeOrder(['user_id' => $user->id], [
        ['product_id' => $cam->id, 'product_name' => 'DJI NEO 2', 'quantity' => 3, 'total' => 300],
        ['product_id' => $strap->id, 'product_name' => 'DJI STRAP', 'quantity' => 8, 'total' => 80],
    ]);

    $this->actingAs($user)
        ->get(route('reports.sales-register', [
            'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'category_id' => $cameras->id,
        ]))
        ->assertInertia(fn ($page) => $page
            ->where('grandTotal.quantity', 3)
            ->where('groups.0.category', 'DIGITAL CAMERA')
        );
});

it('filters by salesperson and by payment method', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $cat = Category::create(['name' => 'DJI']);
    $p = Product::factory()->create(['name' => 'DJI AIR 3', 'category_id' => $cat->id]);

    makeOrder(['user_id' => $alice->id, 'payment_method' => 'cash'], [
        ['product_id' => $p->id, 'product_name' => 'DJI AIR 3', 'quantity' => 1, 'total' => 100],
    ]);
    makeOrder(['user_id' => $bob->id, 'payment_method' => 'card'], [
        ['product_id' => $p->id, 'product_name' => 'DJI AIR 3', 'quantity' => 6, 'total' => 600],
    ]);

    $this->actingAs($alice)
        ->get(route('reports.sales-register', [
            'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'user_id' => $alice->id,
        ]))
        ->assertInertia(fn ($page) => $page->where('grandTotal.quantity', 1));

    $this->actingAs($alice)
        ->get(route('reports.sales-register', [
            'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'payment_method' => 'card',
        ]))
        ->assertInertia(fn ($page) => $page->where('grandTotal.quantity', 6));
});
```

- [ ] **Step 2: Run tests**

Run: `php artisan test tests/Feature/SalesRegisterReportTest.php`
Expected: PASS. If the brand or category test fails, fix `salesRegisterBaseQuery` accordingly (brand pattern must be `LIKE '<brand> %' OR = '<brand>'`; category must filter `products.category_id`).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/SalesRegisterReportTest.php
git commit -m "test(reports): cover sales register filters (brand/category/user/method)"
```

---

## Task 4: Filter option lists (dropdown data)

Populate the dropdowns the Vue page needs.

**Files:**
- Modify: `app/Http/Controllers/ReportController.php`
- Test: `tests/Feature/SalesRegisterReportTest.php`

**Interfaces:**
- Produces: `private function salesRegisterFilterOptions(): array` returning
  `['brands'=>string[], 'categories'=>[{id,name}], 'salespersons'=>[{id,name}], 'customers'=>[{id,name}], 'paymentMethods'=>string[], 'deliveryMethods'=>string[]]`.
  `salesRegister` calls it to fill the `filterOptions` prop.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/SalesRegisterReportTest.php` (`use App\Models\Customer;` at top):

```php
it('provides brand and category filter options', function () {
    $user = User::factory()->create();
    $cat = Category::create(['name' => 'DIGITAL CAMERA']);
    Product::factory()->create(['name' => 'DJI NEO 2', 'category_id' => $cat->id]);
    Product::factory()->create(['name' => 'INSTA360 X4', 'category_id' => $cat->id]);
    Customer::factory()->create(['name' => 'Acme Corp']);

    $this->actingAs($user)
        ->get(route('reports.sales-register'))
        ->assertInertia(fn ($page) => $page
            ->where('filterOptions.brands', fn ($brands) => in_array('DJI', $brands) && in_array('INSTA360', $brands))
            ->where('filterOptions.categories', fn ($cats) => collect($cats)->pluck('name')->contains('DIGITAL CAMERA'))
            ->where('filterOptions.customers', fn ($custs) => collect($custs)->pluck('name')->contains('Acme Corp'))
        );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/SalesRegisterReportTest.php --filter="provides brand and category"`
Expected: FAIL — `filterOptions.brands` is empty.

- [ ] **Step 3: Implement the filter options helper**

In `ReportController.php` add:
```php
    private function salesRegisterFilterOptions(): array
    {
        $brands = Product::pluck('name')
            ->map(fn ($name) => strtok(trim((string) $name), ' '))
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return [
            'brands' => $brands,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'salespersons' => User::orderBy('name')->get(['id', 'name']),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'paymentMethods' => ['cash', 'card', 'bank_transfer'],
            'deliveryMethods' => ['walk-in', 'delivery', 'pickup', 'shopee', 'lazada', 'tiktok'],
        ];
    }
```

Then in `salesRegister`, replace the `'filterOptions' => [ ... empty ... ]` block with:
```php
            'filterOptions' => $this->salesRegisterFilterOptions(),
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/SalesRegisterReportTest.php`
Expected: PASS (whole file green).

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Http/Controllers/ReportController.php
git add app/Http/Controllers/ReportController.php tests/Feature/SalesRegisterReportTest.php
git commit -m "feat(reports): sales register filter option lists"
```

---

## Task 5: Vue page — filter bar, header block, grouped tables

Build the full report UI.

**Files:**
- Modify: `resources/js/pages/Reports/SalesRegister.vue`

**Interfaces:**
- Consumes: props `groups`, `grandTotal`, `filterOptions`, `filters` (shapes from Tasks 2 & 4).

- [ ] **Step 1: Replace the page with the full implementation**

Overwrite `resources/js/pages/Reports/SalesRegister.vue`:
```vue
<template>
    <Head title="Sales Register" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Filter bar -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
                            <input type="date" v-model="filters.start_date" :class="inputClass">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                            <input type="date" v-model="filters.end_date" :class="inputClass">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Brand</label>
                            <select v-model="filters.brand" :class="inputClass">
                                <option value="">All</option>
                                <option v-for="b in filterOptions.brands" :key="b" :value="b">{{ b }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                            <select v-model="filters.category_id" :class="inputClass">
                                <option value="">All</option>
                                <option v-for="c in filterOptions.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sales Person</label>
                            <select v-model="filters.user_id" :class="inputClass">
                                <option value="">All</option>
                                <option v-for="u in filterOptions.salespersons" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact</label>
                            <select v-model="filters.customer_id" :class="inputClass">
                                <option value="">All</option>
                                <option v-for="c in filterOptions.customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Method</label>
                            <select v-model="filters.payment_method" :class="inputClass">
                                <option value="">All</option>
                                <option v-for="m in filterOptions.paymentMethods" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Delivery Method</label>
                            <select v-model="filters.delivery_method" :class="inputClass">
                                <option value="">All</option>
                                <option v-for="m in filterOptions.deliveryMethods" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <button @click="applyFilters" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                            Apply Filters
                        </button>
                        <a :href="route('reports.sales-register.export', queryParams)" target="_blank"
                           class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 flex items-center gap-2">
                            <FileSpreadsheet class="w-4 h-4" /> Export Excel
                        </a>
                        <a :href="route('reports.sales-register.invoices', queryParams)" target="_blank"
                           class="bg-slate-600 text-white px-4 py-2 rounded-md hover:bg-slate-700 flex items-center gap-2">
                            <Download class="w-4 h-4" /> Download Invoices
                        </a>
                    </div>
                </div>

                <!-- Header block mirroring the reference report -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 p-4 text-sm text-gray-700 dark:text-gray-300">
                    <h2 class="text-base font-bold mb-2 text-gray-900 dark:text-gray-100">SALES REGISTER BY PRODUCT TYPE (GROUP BY CATEGORY)</h2>
                    <div><strong>Duration</strong>: from {{ filters.start_date }} to {{ filters.end_date }}</div>
                    <div><strong>Outlet</strong>: All</div>
                    <div><strong>Brand</strong>: {{ filters.brand || 'ALL' }}</div>
                    <div><strong>Category</strong>: {{ selectedCategoryName }}</div>
                    <div><strong>Contact</strong>: {{ selectedCustomerName }}</div>
                    <div><strong>Sales Person</strong>: {{ selectedSalespersonName }}</div>
                    <div><strong>Date Printed</strong>: {{ printedAt }}</div>
                </div>

                <!-- Grouped tables -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <template v-for="group in groups" :key="group.category">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-sm font-bold text-gray-900 dark:text-gray-100">{{ group.category }}</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Quantity</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Sales</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="product in group.products" :key="product.name">
                                        <td class="px-6 py-3 text-gray-900 dark:text-gray-100">{{ product.name }}</td>
                                        <td class="px-6 py-3 text-right text-gray-900 dark:text-gray-100">{{ product.quantity }}</td>
                                        <td class="px-6 py-3 text-right text-gray-900 dark:text-gray-100">{{ formatNumber(product.sales) }}</td>
                                    </tr>
                                    <tr class="bg-gray-50 dark:bg-gray-900 font-semibold">
                                        <td class="px-6 py-3 text-right text-gray-900 dark:text-gray-100">Total</td>
                                        <td class="px-6 py-3 text-right text-gray-900 dark:text-gray-100">{{ group.quantity_total }}</td>
                                        <td class="px-6 py-3 text-right text-gray-900 dark:text-gray-100">{{ formatNumber(group.sales_total) }}</td>
                                    </tr>
                                </tbody>
                            </template>
                            <tfoot class="bg-gray-200 dark:bg-gray-700 font-bold">
                                <tr>
                                    <td class="px-6 py-3 text-right text-gray-900 dark:text-gray-100">Grand Total</td>
                                    <td class="px-6 py-3 text-right text-gray-900 dark:text-gray-100">{{ grandTotal.quantity }}</td>
                                    <td class="px-6 py-3 text-right text-gray-900 dark:text-gray-100">{{ formatNumber(grandTotal.sales) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                        <div v-if="groups.length === 0" class="p-6 text-center text-gray-500 dark:text-gray-400">
                            No sales found for the selected filters.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { FileSpreadsheet, Download } from 'lucide-vue-next';

const props = defineProps({
    groups: Array,
    grandTotal: Object,
    filterOptions: Object,
    filters: Object,
});

const inputClass = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2';

const filters = ref({
    start_date: '',
    end_date: '',
    brand: '',
    category_id: '',
    user_id: '',
    customer_id: '',
    payment_method: '',
    delivery_method: '',
    ...props.filters,
});

const breadcrumbs = [
    { title: 'Sales Register', href: route('reports.sales-register') },
];

const queryParams = computed(() => ({
    start_date: filters.value.start_date,
    end_date: filters.value.end_date,
    brand: filters.value.brand || undefined,
    category_id: filters.value.category_id || undefined,
    user_id: filters.value.user_id || undefined,
    customer_id: filters.value.customer_id || undefined,
    payment_method: filters.value.payment_method || undefined,
    delivery_method: filters.value.delivery_method || undefined,
}));

const selectedCategoryName = computed(() => {
    const c = props.filterOptions.categories.find((x) => String(x.id) === String(filters.value.category_id));
    return c ? c.name : 'ALL';
});
const selectedCustomerName = computed(() => {
    const c = props.filterOptions.customers.find((x) => String(x.id) === String(filters.value.customer_id));
    return c ? c.name : 'ALL';
});
const selectedSalespersonName = computed(() => {
    const u = props.filterOptions.salespersons.find((x) => String(x.id) === String(filters.value.user_id));
    return u ? u.name : 'ALL';
});
const printedAt = computed(() => new Date().toLocaleString());

function formatNumber(value) {
    return parseFloat(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function applyFilters() {
    router.get(route('reports.sales-register'), queryParams.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>
```

- [ ] **Step 2: Build the frontend to check for compile errors**

Run: `npm run build`
Expected: Build succeeds with no errors referencing `SalesRegister.vue`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/Reports/SalesRegister.vue
git commit -m "feat(reports): sales register report UI (filters, header, grouped tables)"
```

---

## Task 6: Excel export

Export the grouped layout to xlsx.

**Files:**
- Modify: `app/Http/Controllers/ReportController.php`
- Test: `tests/Feature/SalesRegisterReportTest.php`

**Interfaces:**
- Consumes: `salesRegisterBaseQuery` (Task 2).
- Produces: `salesRegisterExport(Request $request)` streaming an xlsx download.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/SalesRegisterReportTest.php`:
```php
it('exports the sales register as an xlsx download', function () {
    $user = User::factory()->create();
    $cat = Category::create(['name' => 'DJI']);
    $p = Product::factory()->create(['name' => 'DJI AIR 3', 'category_id' => $cat->id]);
    makeOrder(['user_id' => $user->id], [
        ['product_id' => $p->id, 'product_name' => 'DJI AIR 3', 'quantity' => 2, 'total' => 200],
    ]);

    $response = $this->actingAs($user)->get(route('reports.sales-register.export', [
        'start_date' => '2026-06-01', 'end_date' => '2026-06-30',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/SalesRegisterReportTest.php --filter="exports the sales register"`
Expected: FAIL — method `salesRegisterExport` does not exist (error/500).

- [ ] **Step 3: Implement the export**

Add to `ReportController.php`:
```php
    public function salesRegisterExport(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        $rows = $this->salesRegisterBaseQuery($request)
            ->select(
                DB::raw("COALESCE(categories.name, 'Uncategorized') as category"),
                'order_items.product_name as product_name',
                DB::raw('SUM(order_items.quantity) as quantity'),
                DB::raw('SUM(order_items.total) as sales')
            )
            ->groupBy('category', 'order_items.product_name')
            ->orderBy('category')
            ->orderBy('order_items.product_name')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Product');
        $sheet->setCellValue('B1', 'Quantity');
        $sheet->setCellValue('C1', 'Sales');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);

        $row = 2;
        $currentCategory = null;
        $catQty = 0;
        $catSales = 0.0;
        $grandQty = 0;
        $grandSales = 0.0;

        $writeCategoryTotal = function () use ($sheet, &$row, &$catQty, &$catSales) {
            $sheet->setCellValue('A' . $row, 'Total');
            $sheet->setCellValue('B' . $row, $catQty);
            $sheet->setCellValue('C' . $row, $catSales);
            $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
            $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $row++;
        };

        foreach ($rows as $r) {
            if ($currentCategory !== $r->category) {
                if ($currentCategory !== null) {
                    $writeCategoryTotal();
                    $catQty = 0;
                    $catSales = 0.0;
                }
                $currentCategory = $r->category;
                $sheet->setCellValue('A' . $row, $r->category);
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;
            }

            $qty = (int) $r->quantity;
            $sales = (float) $r->sales;

            $sheet->setCellValue('A' . $row, $r->product_name);
            $sheet->setCellValue('B' . $row, $qty);
            $sheet->setCellValue('C' . $row, $sales);
            $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $row++;

            $catQty += $qty;
            $catSales += $sales;
            $grandQty += $qty;
            $grandSales += $sales;
        }

        if ($currentCategory !== null) {
            $writeCategoryTotal();
        }

        $sheet->setCellValue('A' . $row, 'Grand Total');
        $sheet->setCellValue('B' . $row, $grandQty);
        $sheet->setCellValue('C' . $row, $grandSales);
        $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
        $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

        foreach (range('A', 'C') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'sales-register-' . $startDate . '-to-' . $endDate . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/SalesRegisterReportTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Http/Controllers/ReportController.php
git add app/Http/Controllers/ReportController.php tests/Feature/SalesRegisterReportTest.php
git commit -m "feat(reports): sales register Excel export"
```

---

## Task 7: Download Invoices bundle PDF

Bundle every matching order's invoice into one PDF, reusing the existing invoice markup.

**Files:**
- Create: `resources/views/pdf/partials/invoice-body.blade.php`
- Modify: `resources/views/pdf/invoice.blade.php`
- Create: `resources/views/pdf/invoices-bundle.blade.php`
- Modify: `app/Http/Controllers/ReportController.php`
- Test: `tests/Feature/SalesRegisterReportTest.php`

**Interfaces:**
- Consumes: order filters (date/user/customer/payment/delivery are order-level; brand/category are item-level).
- Produces:
  - `private function salesRegisterMatchingOrders(Request $request)` → `Collection<Order>` with `items`, `customer`, `user` eager-loaded.
  - `salesRegisterInvoices(Request $request)` → PDF stream, or redirect back with an error when no orders match.
  - Shared Blade partial `pdf.partials.invoice-body` rendering one invoice given `$order`, `$settings`, `$isQueued`, `$qrCodeBase64`, `$queueDelayHours`.

- [ ] **Step 1: Extract the invoice body into a shared partial**

Create `resources/views/pdf/partials/invoice-body.blade.php` containing **exactly** the current body of `resources/views/pdf/invoice.blade.php` — that is, the `<div class="container"> … </div>` block spanning lines 54–244 (from `<div class="container">` through its matching `</div>` just before `</body>`). Move that block verbatim into the new partial file.

- [ ] **Step 2: Reference the partial from invoice.blade.php**

In `resources/views/pdf/invoice.blade.php`, replace the moved `<div class="container"> … </div>` block (lines 54–244) with a single line:
```blade
    @include('pdf.partials.invoice-body')
```
Leave everything else (`<!DOCTYPE html>`, `<head>`, `<style>`, `<body>`, `</body>`, `</html>`) unchanged.

- [ ] **Step 3: Verify the single-invoice PDF still renders**

Run: `php artisan test`
Expected: existing suite stays green (no regression from the extraction). Then, if a dev server is handy, open `/orders/{id}/invoice` for any order and confirm the PDF looks identical. (Manual check optional; the automated regression gate is Step 7.)

- [ ] **Step 4: Create the bundle Blade**

Create `resources/views/pdf/invoices-bundle.blade.php`:
```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoices</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; }
        .container { padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f8f9fa; }
        .invoice-page { page-break-after: always; }
        .invoice-page:last-child { page-break-after: auto; }
    </style>
</head>
<body>
    @foreach($orders as $order)
        <div class="invoice-page">
            @include('pdf.partials.invoice-body', [
                'order' => $order,
                'settings' => $settings,
                'isQueued' => false,
                'qrCodeBase64' => null,
                'queueDelayHours' => $queueDelayHours,
            ])
        </div>
    @endforeach
</body>
</html>
```

- [ ] **Step 5: Write the failing test**

Add to `tests/Feature/SalesRegisterReportTest.php`. (No new import needed — `makeShopSettings()` is a global helper already defined in `tests/Pest.php`, and `RefreshDatabase` is auto-applied to Feature tests.)
```php
it('bundles matching orders into a single invoices PDF', function () {
    makeShopSettings();
    $user = User::factory()->create();
    $cat = Category::create(['name' => 'DJI']);
    $p = Product::factory()->create(['name' => 'DJI AIR 3', 'category_id' => $cat->id]);
    makeOrder(['user_id' => $user->id], [
        ['product_id' => $p->id, 'product_name' => 'DJI AIR 3', 'quantity' => 1, 'price' => 100, 'total' => 100],
    ]);

    $response = $this->actingAs($user)->get(route('reports.sales-register.invoices', [
        'start_date' => '2026-06-01', 'end_date' => '2026-06-30',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('redirects back when no orders match the invoices bundle', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.sales-register.invoices', [
            'start_date' => '2026-06-01', 'end_date' => '2026-06-30',
        ]))
        ->assertRedirect();
});
```

- [ ] **Step 6: Run tests to verify they fail**

Run: `php artisan test tests/Feature/SalesRegisterReportTest.php --filter="invoices"`
Expected: FAIL — `salesRegisterInvoices` not defined.

- [ ] **Step 7: Implement the controller method**

Add these `use` statements to `ReportController.php` (top of file):
```php
use App\Models\ShopSettings;
use Barryvdh\DomPDF\Facade\Pdf;
```
Add the methods:
```php
    private function salesRegisterMatchingOrders(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        return Order::query()
            ->with(['items', 'customer', 'user'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status', '!=', 'cancelled')
            ->when($request->input('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->input('customer_id'), fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->input('payment_method'), fn ($q, $m) => $q->where('payment_method', $m))
            ->when($request->input('delivery_method'), fn ($q, $m) => $q->where('delivery_method', $m))
            ->when($request->input('category_id'), fn ($q, $id) => $q->whereHas('items.product', fn ($p) => $p->where('category_id', $id)))
            ->when($request->input('brand'), function ($q, $brand) {
                $q->whereHas('items', function ($i) use ($brand) {
                    $i->where('product_name', 'like', $brand . ' %')
                      ->orWhere('product_name', $brand);
                });
            })
            ->orderBy('created_at')
            ->get();
    }

    public function salesRegisterInvoices(Request $request)
    {
        $orders = $this->salesRegisterMatchingOrders($request);

        if ($orders->isEmpty()) {
            return back()->with('error', 'No invoices found for the selected filters.');
        }

        $pdf = Pdf::loadView('pdf.invoices-bundle', [
            'orders' => $orders,
            'settings' => ShopSettings::first(),
            'queueDelayHours' => config('services.myinvois.queue_delay_hours', 72),
        ]);

        return $pdf->stream('sales-register-invoices.pdf');
    }
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test tests/Feature/SalesRegisterReportTest.php`
Expected: PASS (whole file green).

- [ ] **Step 9: Full regression + build**

Run: `php artisan test`
Expected: entire suite green (confirms the invoice-body extraction did not break existing invoice/PDF tests).
Run: `npm run build`
Expected: succeeds.

- [ ] **Step 10: Commit**

```bash
./vendor/bin/pint app/Http/Controllers/ReportController.php
git add app/Http/Controllers/ReportController.php resources/views/pdf/partials/invoice-body.blade.php resources/views/pdf/invoice.blade.php resources/views/pdf/invoices-bundle.blade.php tests/Feature/SalesRegisterReportTest.php
git commit -m "feat(reports): sales register Download Invoices bundle PDF"
```

---

## Self-Review Notes

- **Spec coverage:** route/controller/page (Task 1); grouping, gross sales, exclusions, date range (Task 2); brand/category/salesperson/customer/method filters (Task 3); filter option lists incl. all-first-words brand list (Task 4); filter bar + header block + grouped tables + grand total + Download/Export buttons (Task 5); Excel export (Task 6); combined invoices PDF reusing `pdf.invoice` via shared partial, empty-match handling (Task 7). Nav link + role visibility (Task 1). All spec sections mapped.
- **Uncategorized bucket:** handled via `COALESCE(categories.name, 'Uncategorized')` in Tasks 2 & 6.
- **Brand-in-bundle QR:** bundle passes `isQueued=false`, `qrCodeBase64=null` to avoid per-order network calls; the E-Invoice QR block is skipped by the partial's `@if($isQueued && $qrCodeBase64)` guard. Intentional.
- **Type consistency:** prop names (`groups`, `grandTotal`, `filterOptions`, `filters`) and product shape (`name`/`quantity`/`sales`) identical across controller, Vue, tests. Helper names (`salesRegisterBaseQuery`, `salesRegisterFilterOptions`, `salesRegisterMatchingOrders`) consistent across tasks.
- **Factories:** Only `UserFactory` existed. Task 2 Step 1 creates `CategoryFactory`, `ProductFactory`, `CustomerFactory`, `OrderFactory` with the correct required columns and enum `status='active'`. `created_at` is set via `forceFill` because it is not mass-assignable.
- **Required-column pitfalls encoded in tests:** orders need `subtotal/tax/total/paid_amount`; products need `price` + `category_id`; `shop_settings` needs `shop_name/shop_address/shop_phone/shop_email`; `categories.status`/`products.status` are enums, never booleans.

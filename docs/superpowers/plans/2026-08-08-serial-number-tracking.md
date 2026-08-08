# Serial-Number (S/N) Tracking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let selected products track individual serial numbers — staff add stock by keying/scanning serials, and sell by picking the specific serial(s) — layered on top of the existing aggregate-stock system.

**Architecture:** A per-product `serial_tracked` flag opts a product into serial tracking. A new `product_serials` table holds one row per unit with a status (`available`/`sold`) and links to the order it sold on. A `ProductSerialService` centralizes add/remove/allocate/release and keeps `products.stock` equal to the count of `available` serials, so POS listing, reports, and MyInvois need no changes. Untracked products behave exactly as today.

**Tech Stack:** Laravel 12 (PHP 8.2), SQLite, Pest tests, Spatie Permissions, Vue 3 + Inertia + TypeScript, Tailwind.

## Global Constraints

- All models use soft deletes; `Product` and `ProductSerial` also use OwenIt Auditing.
- Serial numbers are **globally unique among live (non-soft-deleted) rows**.
- For tracked products, `products.stock` MUST always equal the count of `available` serials — never mutate it directly for tracked products; always go through `ProductSerialService::syncStock()`.
- At order time for tracked products, **quantity = number of serials picked** (server-derived, never trusts client quantity).
- On order void/cancel/edit, sold serials return to `available` and their order links are cleared.
- Untracked-product code paths (integer `increment`/`decrement`) must remain unchanged.
- Follow existing conventions: `DB::beginTransaction()` wrapping in controllers, `Rule::unique(...)` validation, Inertia `useForm`, `preserveScroll`.
- Serials are NOT added to the MyInvois e-invoice payload (out of scope).

---

## File Structure

**New files:**
- `database/migrations/2026_08_08_000001_add_serial_tracked_to_products_table.php`
- `database/migrations/2026_08_08_000002_create_product_serials_table.php`
- `app/Models/ProductSerial.php`
- `database/factories/ProductSerialFactory.php`
- `app/Services/ProductSerialService.php`
- `tests/Feature/SerialTrackingTest.php`

**Modified files:**
- `app/Models/Product.php` — fillable, cast, `serials()` relationship
- `app/Http/Controllers/ProductController.php` — serial endpoints, `adjustStock` guard, `store`/`update`/`getPosProducts`/`show` changes
- `app/Http/Controllers/OrderController.php` — allocate/release across `store`, `update`, `destroy`, `updateStatus`
- `routes/pos.php` — serial routes
- `resources/js/pages/Products/Create.vue`, `Edit.vue` — `serial_tracked` toggle
- `resources/js/pages/Products/Show.vue` — serial management panel
- `resources/js/pages/Orders/Create.vue`, `Edit.vue` — serial picker + read-only derived quantity

---

## Task 1: Data model — migrations, models, factory

**Files:**
- Create: `database/migrations/2026_08_08_000001_add_serial_tracked_to_products_table.php`
- Create: `database/migrations/2026_08_08_000002_create_product_serials_table.php`
- Create: `app/Models/ProductSerial.php`
- Create: `database/factories/ProductSerialFactory.php`
- Modify: `app/Models/Product.php:17-47`
- Test: `tests/Feature/SerialTrackingTest.php`

**Interfaces:**
- Produces:
  - `products.serial_tracked` (bool, default false)
  - `product_serials` columns: `id, product_id, serial_number, status ('available'|'sold'), order_item_id?, order_id?, timestamps, deleted_at`
  - `Product::serials(): HasMany`
  - `ProductSerial` model with `product()`, `orderItem()`, `order()`, scope `available()`, fillable `['product_id','serial_number','status','order_item_id','order_id']`
  - `ProductSerial::factory()`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/SerialTrackingTest.php`:

```php
<?php

use App\Models\Product;
use App\Models\ProductSerial;

it('relates serials to a product and scopes available ones', function () {
    $product = Product::factory()->create(['serial_tracked' => true]);

    ProductSerial::create(['product_id' => $product->id, 'serial_number' => 'SN-A', 'status' => 'available']);
    ProductSerial::create(['product_id' => $product->id, 'serial_number' => 'SN-B', 'status' => 'sold']);

    expect($product->serials()->count())->toBe(2);
    expect($product->serials()->available()->count())->toBe(1);
    expect(ProductSerial::first()->product->id)->toBe($product->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/SerialTrackingTest.php`
Expected: FAIL — `serial_tracked` column / `product_serials` table / `ProductSerial` model don't exist.

- [ ] **Step 3: Write the migrations, model, and factory**

`database/migrations/2026_08_08_000001_add_serial_tracked_to_products_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('serial_tracked')->default(false)->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('serial_tracked');
        });
    }
};
```

`database/migrations/2026_08_08_000002_create_product_serials_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('serial_number');
            $table->enum('status', ['available', 'sold'])->default('available');
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['product_id', 'status']);
        });

        // Global uniqueness among LIVE rows only (SQLite partial unique index):
        // soft-deleted serial numbers may be re-added.
        DB::statement('CREATE UNIQUE INDEX product_serials_serial_number_unique ON product_serials (serial_number) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS product_serials_serial_number_unique');
        Schema::dropIfExists('product_serials');
    }
};
```

`app/Models/ProductSerial.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class ProductSerial extends Model implements Auditable
{
    use HasFactory, SoftDeletes, AuditableTrait;

    protected $fillable = [
        'product_id',
        'serial_number',
        'status',
        'order_item_id',
        'order_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }
}
```

`database/factories/ProductSerialFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductSerial;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductSerialFactory extends Factory
{
    protected $model = ProductSerial::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'serial_number' => strtoupper($this->faker->unique()->bothify('SN-####-????')),
            'status' => 'available',
        ];
    }
}
```

Modify `app/Models/Product.php`:
- In `$fillable` (line 17-28) add `'serial_tracked',`.
- In `$casts` (line 30-35) add `'serial_tracked' => 'boolean',`.
- Add relationship after `supplier()` (line 47):

```php
    public function serials(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductSerial::class);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/SerialTrackingTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations app/Models/ProductSerial.php app/Models/Product.php database/factories/ProductSerialFactory.php tests/Feature/SerialTrackingTest.php
git commit -m "feat(serials): add product_serials table, model, and serial_tracked flag"
```

---

## Task 2: ProductSerialService — add/remove/allocate/release/syncStock

**Files:**
- Create: `app/Services/ProductSerialService.php`
- Test: `tests/Feature/SerialTrackingTest.php`

**Interfaces:**
- Consumes: `Product::serials()`, `ProductSerial`, `OrderItem` from Task 1.
- Produces `App\Services\ProductSerialService` with:
  - `syncStock(Product $product): void` — sets `product.stock = available serial count`.
  - `addSerials(Product $product, array $serialNumbers): void` — inserts `available` rows, then syncStock.
  - `removeSerial(ProductSerial $serial): void` — soft-deletes (only if available), then syncStock.
  - `allocate(OrderItem $orderItem, Product $product, array $serialNumbers): int` — locks & marks `sold`, sets `order_id`+`order_item_id`, syncStock; throws `\RuntimeException` if any requested serial isn't available. Returns count.
  - `release(int $orderId): void` — flips `sold`→`available` for that order, nulls links, syncStock for affected products.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/SerialTrackingTest.php`:

```php
use App\Models\OrderItem;
use App\Services\ProductSerialService;

it('syncs product stock to the count of available serials', function () {
    $product = Product::factory()->create(['serial_tracked' => true, 'stock' => 0]);
    $service = app(ProductSerialService::class);

    $service->addSerials($product, ['SN-1', 'SN-2', 'SN-3']);

    expect($product->fresh()->stock)->toBe(3);
    expect($product->serials()->available()->count())->toBe(3);
});

it('removing an available serial recounts stock', function () {
    $product = Product::factory()->create(['serial_tracked' => true]);
    $service = app(ProductSerialService::class);
    $service->addSerials($product, ['SN-1', 'SN-2']);

    $serial = $product->serials()->where('serial_number', 'SN-1')->first();
    $service->removeSerial($serial);

    expect($product->fresh()->stock)->toBe(1);
    expect($product->serials()->count())->toBe(1);
});

it('allocates serials to an order item, marks them sold, and links the order', function () {
    $product = Product::factory()->create(['serial_tracked' => true]);
    $order = makeOrder();
    $service = app(ProductSerialService::class);
    $service->addSerials($product, ['SN-1', 'SN-2', 'SN-3']);

    $item = OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
        'quantity' => 2, 'price' => 10, 'cost_price' => 0, 'total' => 20, 'profit' => 20,
    ]);

    $count = $service->allocate($item, $product, ['SN-1', 'SN-2']);

    expect($count)->toBe(2);
    expect($product->fresh()->stock)->toBe(1);
    $sold = $product->serials()->where('status', 'sold')->get();
    expect($sold->pluck('serial_number')->sort()->values()->all())->toBe(['SN-1', 'SN-2']);
    expect($sold->every(fn ($s) => $s->order_id === $order->id && $s->order_item_id === $item->id))->toBeTrue();
});

it('throws when allocating a serial that is not available', function () {
    $product = Product::factory()->create(['serial_tracked' => true]);
    $order = makeOrder();
    $service = app(ProductSerialService::class);
    $service->addSerials($product, ['SN-1']);
    $item = OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
        'quantity' => 1, 'price' => 10, 'cost_price' => 0, 'total' => 10, 'profit' => 10,
    ]);

    $service->allocate($item, $product, ['SN-1', 'SN-DOES-NOT-EXIST']);
})->throws(\RuntimeException::class);

it('releases serials for an order back to the available pool', function () {
    $product = Product::factory()->create(['serial_tracked' => true]);
    $order = makeOrder();
    $service = app(ProductSerialService::class);
    $service->addSerials($product, ['SN-1', 'SN-2']);
    $item = OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
        'quantity' => 2, 'price' => 10, 'cost_price' => 0, 'total' => 20, 'profit' => 20,
    ]);
    $service->allocate($item, $product, ['SN-1', 'SN-2']);

    $service->release($order->id);

    expect($product->fresh()->stock)->toBe(2);
    expect($product->serials()->available()->count())->toBe(2);
    expect(ProductSerial::where('order_id', $order->id)->count())->toBe(0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/SerialTrackingTest.php`
Expected: FAIL — `ProductSerialService` does not exist.

- [ ] **Step 3: Write the service**

`app/Services/ProductSerialService.php`:

```php
<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSerial;

class ProductSerialService
{
    public function syncStock(Product $product): void
    {
        $count = $product->serials()->where('status', 'available')->count();
        $product->forceFill(['stock' => $count])->save();
    }

    public function addSerials(Product $product, array $serialNumbers): void
    {
        foreach ($serialNumbers as $serial) {
            $product->serials()->create([
                'serial_number' => $serial,
                'status' => 'available',
            ]);
        }

        $this->syncStock($product);
    }

    public function removeSerial(ProductSerial $serial): void
    {
        if ($serial->status !== 'available') {
            throw new \RuntimeException('Only available serials can be removed.');
        }

        $product = $serial->product;
        $serial->delete();
        $this->syncStock($product);
    }

    public function allocate(OrderItem $orderItem, Product $product, array $serialNumbers): int
    {
        $serialNumbers = array_values(array_unique($serialNumbers));

        $serials = $product->serials()
            ->where('status', 'available')
            ->whereIn('serial_number', $serialNumbers)
            ->lockForUpdate()
            ->get();

        if ($serials->count() !== count($serialNumbers)) {
            throw new \RuntimeException("Some serial numbers are not available for product: {$product->name}");
        }

        foreach ($serials as $serial) {
            $serial->update([
                'status' => 'sold',
                'order_id' => $orderItem->order_id,
                'order_item_id' => $orderItem->id,
            ]);
        }

        $this->syncStock($product);

        return $serials->count();
    }

    public function release(int $orderId): void
    {
        $serials = ProductSerial::where('order_id', $orderId)
            ->where('status', 'sold')
            ->get();

        $productIds = $serials->pluck('product_id')->unique();

        foreach ($serials as $serial) {
            $serial->update([
                'status' => 'available',
                'order_id' => null,
                'order_item_id' => null,
            ]);
        }

        foreach ($productIds as $productId) {
            if ($product = Product::find($productId)) {
                $this->syncStock($product);
            }
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/SerialTrackingTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ProductSerialService.php tests/Feature/SerialTrackingTest.php
git commit -m "feat(serials): add ProductSerialService for add/remove/allocate/release"
```

---

## Task 3: ProductController serial endpoints, validation, and routes

**Files:**
- Modify: `app/Http/Controllers/ProductController.php:52-148,175-181`
- Modify: `routes/pos.php:18-43`
- Test: `tests/Feature/SerialTrackingTest.php`

**Interfaces:**
- Consumes: `ProductSerialService` (Task 2).
- Produces routes:
  - `GET products/{product}/serials` → `products.serials.index` (auth group)
  - `POST products/{product}/serials` → `products.serials.store` (admin|manager)
  - `DELETE products/{product}/serials/{serial}` → `products.serials.destroy` (admin|manager)
- `getPosProducts` JSON now includes `serial_tracked`; `show` eager-loads `serials`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/SerialTrackingTest.php` (reuse the `registerAdmin()` helper pattern from `SalesRegisterReportTest.php`; define a local one at the top of this file if not already present):

```php
use App\Models\User;

function serialAdmin(): User
{
    \Spatie\Permission\Models\Role::findOrCreate('admin');
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('bulk-adds serials via the endpoint and syncs stock', function () {
    $admin = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true, 'stock' => 0]);

    $this->actingAs($admin)
        ->post(route('products.serials.store', $product), ['serials' => ['SN-1', 'SN-2', 'SN-3']])
        ->assertRedirect();

    expect($product->fresh()->stock)->toBe(3);
});

it('rejects a globally-duplicate serial on add', function () {
    $admin = serialAdmin();
    $p1 = Product::factory()->create(['serial_tracked' => true]);
    $p2 = Product::factory()->create(['serial_tracked' => true]);
    ProductSerial::create(['product_id' => $p1->id, 'serial_number' => 'DUP', 'status' => 'available']);

    $this->actingAs($admin)
        ->post(route('products.serials.store', $p2), ['serials' => ['DUP']])
        ->assertSessionHasErrors('serials.0');

    expect($p2->fresh()->stock)->toBe(0);
});

it('rejects duplicate serials within the same request', function () {
    $admin = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true]);

    $this->actingAs($admin)
        ->post(route('products.serials.store', $product), ['serials' => ['SAME', 'SAME']])
        ->assertSessionHasErrors();

    expect($product->fresh()->stock)->toBe(0);
});

it('removes an available serial via the endpoint', function () {
    $admin = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true]);
    app(ProductSerialService::class)->addSerials($product, ['SN-1', 'SN-2']);
    $serial = $product->serials()->where('serial_number', 'SN-1')->first();

    $this->actingAs($admin)
        ->delete(route('products.serials.destroy', [$product, $serial]))
        ->assertRedirect();

    expect($product->fresh()->stock)->toBe(1);
});

it('rejects integer stock adjustment for a serial-tracked product', function () {
    $admin = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true]);

    $this->actingAs($admin)
        ->post(route('products.adjust-stock', $product), ['quantity' => 5, 'type' => 'restock'])
        ->assertSessionHas('error');
});

it('forces stock to zero when creating a serial-tracked product', function () {
    $admin = serialAdmin();
    $category = \App\Models\Category::factory()->create();

    $this->actingAs($admin)->post(route('products.store'), [
        'name' => 'Tracked Device', 'price' => 100, 'cost_price' => 50,
        'stock' => 99, 'category_id' => $category->id, 'status' => 'active',
        'serial_tracked' => true,
    ])->assertRedirect();

    expect(Product::where('name', 'Tracked Device')->first()->stock)->toBe(0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/SerialTrackingTest.php`
Expected: FAIL — routes/methods don't exist.

- [ ] **Step 3: Implement controller methods, guards, and routes**

In `app/Http/Controllers/ProductController.php`, add `use App\Models\ProductSerial;` and `use App\Services\ProductSerialService;` at the top with the other imports.

Add serial methods (place after `adjustStock`, around line 219):

```php
    public function getSerials(Product $product)
    {
        return response()->json([
            'serials' => $product->serials()
                ->where('status', 'available')
                ->orderBy('serial_number')
                ->get(['id', 'serial_number', 'status']),
        ]);
    }

    public function addSerials(Request $request, Product $product, ProductSerialService $service)
    {
        if (! $product->serial_tracked) {
            return back()->with('error', 'This product is not serial-tracked.');
        }

        $validated = $request->validate([
            'serials' => ['required', 'array', 'min:1'],
            'serials.*' => [
                'required', 'string', 'max:255', 'distinct',
                \Illuminate\Validation\Rule::unique('product_serials', 'serial_number')->whereNull('deleted_at'),
            ],
        ]);

        $serials = array_map('trim', $validated['serials']);
        $service->addSerials($product, $serials);

        return back()->with('success', 'Serials added successfully.');
    }

    public function removeSerial(Product $product, ProductSerial $serial, ProductSerialService $service)
    {
        if ($serial->product_id !== $product->id) {
            abort(404);
        }

        if ($serial->status !== 'available') {
            return back()->with('error', 'Only available serials can be removed.');
        }

        $service->removeSerial($serial);

        return back()->with('success', 'Serial removed successfully.');
    }
```

Guard `adjustStock` — insert at the very start of the method body (before the existing `$request->validate(...)` at line 177):

```php
        if ($product->serial_tracked) {
            return back()->with('error', 'Use serial management for this product.');
        }
```

`store` validation (line 54-65) — add:

```php
            'serial_tracked' => 'boolean',
```

Then, after the `if ($request->hasFile('image'))` block and before `Product::create($validated)` (line 71), force stock to zero for tracked products:

```php
        if ($request->boolean('serial_tracked')) {
            $validated['stock'] = 0;
        }
```

`update` validation (line 87-98) — add `'serial_tracked' => 'boolean',` and guard against enabling tracking on a product that still has stock. After the validate block, before `$product->update($validated)` (line 108):

```php
        if ($request->boolean('serial_tracked') && ! $product->serial_tracked && $product->stock > 0) {
            return back()->with('error', 'Reduce stock to zero before enabling serial tracking.');
        }
```

`getPosProducts` (line 130) — add `serial_tracked` to the select:

```php
            ->select('id', 'name', 'price', 'stock', 'barcode', 'category_id', 'image', 'serial_tracked')
```

`show` (line 146) — eager-load serials:

```php
            'product' => $product->load(['category', 'supplier', 'serials' => fn ($q) => $q->where('status', 'available')->orderBy('serial_number')])
```

Routes in `routes/pos.php`:
- In the base `auth,verified` group (after line 20), add the read endpoint:

```php
    Route::get('products/{product}/serials', [ProductController::class, 'getSerials'])->name('products.serials.index');
```

- In the `role:admin|manager` group (line 38-43), add the mutating endpoints:

```php
        Route::post('products/{product}/serials', [ProductController::class, 'addSerials'])->name('products.serials.store');
        Route::delete('products/{product}/serials/{serial}', [ProductController::class, 'removeSerial'])->name('products.serials.destroy');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/SerialTrackingTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ProductController.php routes/pos.php tests/Feature/SerialTrackingTest.php
git commit -m "feat(serials): product serial endpoints, adjustStock guard, and validation"
```

---

## Task 4: OrderController@store — allocate serials, derive quantity

**Files:**
- Modify: `app/Http/Controllers/OrderController.php:452-535`
- Test: `tests/Feature/SerialTrackingTest.php`

**Interfaces:**
- Consumes: `ProductSerialService::allocate()`.
- Produces: `store` accepts `items.*.serials` (array of strings); for tracked products the order item's `quantity` equals the serial count and serials are marked sold.

- [ ] **Step 1: Write the failing tests**

Append (the store endpoint requires an authenticated user; use `serialAdmin()`):

```php
it('creates an order for a tracked product with quantity derived from serials', function () {
    makeShopSettings();
    $user = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true, 'price' => 100, 'cost_price' => 40]);
    app(ProductSerialService::class)->addSerials($product, ['SN-1', 'SN-2', 'SN-3']);

    $this->actingAs($user)->postJson(route('orders.store'), [
        'items' => [[
            'id' => $product->id, 'quantity' => 999, 'price' => 100,
            'serials' => ['SN-1', 'SN-2'],
        ]],
        'subtotal' => 200, 'tax' => 0, 'delivery_cost' => 0, 'total' => 200,
        'paid_amount' => 200, 'due_amount' => 0, 'change_amount' => 0, 'discount' => 0,
        'payment_method' => 'cash', 'delivery_method' => 'pickup',
    ])->assertJson(['success' => true]);

    $product->refresh();
    expect($product->stock)->toBe(1);
    expect($product->serials()->where('status', 'sold')->count())->toBe(2);

    $item = \App\Models\OrderItem::where('product_id', $product->id)->first();
    expect($item->quantity)->toBe(2); // derived from serials, not the client's 999
});

it('rolls back an order when a requested serial is unavailable', function () {
    makeShopSettings();
    $user = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true, 'price' => 100]);
    app(ProductSerialService::class)->addSerials($product, ['SN-1']);

    $this->actingAs($user)->postJson(route('orders.store'), [
        'items' => [[
            'id' => $product->id, 'quantity' => 2, 'price' => 100,
            'serials' => ['SN-1', 'SN-GHOST'],
        ]],
        'subtotal' => 200, 'tax' => 0, 'delivery_cost' => 0, 'total' => 200,
        'paid_amount' => 200, 'due_amount' => 0, 'change_amount' => 0, 'discount' => 0,
        'payment_method' => 'cash', 'delivery_method' => 'pickup',
    ])->assertStatus(422);

    expect($product->fresh()->stock)->toBe(1); // unchanged, no partial write
    expect(\App\Models\Order::count())->toBe(0);
});

it('handles a mixed order with tracked and untracked products', function () {
    makeShopSettings();
    $user = serialAdmin();
    $tracked = Product::factory()->create(['serial_tracked' => true, 'price' => 100]);
    $untracked = Product::factory()->create(['serial_tracked' => false, 'stock' => 10, 'price' => 20]);
    app(ProductSerialService::class)->addSerials($tracked, ['SN-1', 'SN-2']);

    $this->actingAs($user)->postJson(route('orders.store'), [
        'items' => [
            ['id' => $tracked->id, 'quantity' => 1, 'price' => 100, 'serials' => ['SN-1']],
            ['id' => $untracked->id, 'quantity' => 3, 'price' => 20],
        ],
        'subtotal' => 160, 'tax' => 0, 'delivery_cost' => 0, 'total' => 160,
        'paid_amount' => 160, 'due_amount' => 0, 'change_amount' => 0, 'discount' => 0,
        'payment_method' => 'cash', 'delivery_method' => 'pickup',
    ])->assertJson(['success' => true]);

    expect($tracked->fresh()->stock)->toBe(1);
    expect($untracked->fresh()->stock)->toBe(7);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/SerialTrackingTest.php`
Expected: FAIL — serials aren't allocated; tracked quantity not derived.

- [ ] **Step 3: Implement allocation in `store`**

Add `use App\Services\ProductSerialService;` to `OrderController` imports.

In `store` validation (line 454-472) add:

```php
            'items.*.serials' => 'nullable|array',
            'items.*.serials.*' => 'string',
```

Replace the item loop body (lines 512-535) so tracked products derive quantity and allocate serials. The key change: for tracked products, compute `$quantity` from serials and call `allocate()` instead of `decrement`:

```php
            $service = app(ProductSerialService::class);

            foreach ($validated['items'] as $item) {
                $product = Product::find($item['id']);

                if ($product->serial_tracked) {
                    $serials = array_values(array_unique(array_map('trim', $item['serials'] ?? [])));
                    $quantity = count($serials);

                    if ($quantity < 1) {
                        throw new \Exception("No serial numbers selected for product: {$product->name}");
                    }

                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['id'],
                        'product_name' => $product->name,
                        'quantity' => $quantity,
                        'price' => $item['price'],
                        'cost_price' => $product->cost_price,
                        'total' => $item['price'] * $quantity,
                        'profit' => ($item['price'] - $product->cost_price) * $quantity,
                        'remark' => $item['remark'] ?? null,
                    ]);

                    $service->allocate($orderItem, $product, $serials);

                    continue;
                }

                // Untracked product — existing aggregate-stock path
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'product_name' => $product->name,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'cost_price' => $product->cost_price,
                    'total' => $item['price'] * $item['quantity'],
                    'profit' => ($item['price'] - $product->cost_price) * $item['quantity'],
                    'remark' => $item['remark'] ?? null,
                ]);

                $product->decrement('stock', $item['quantity']);
            }
```

Note: `allocate()` throws `\RuntimeException`, which is caught by the existing `catch (\Exception $e)` at line 555 (RuntimeException extends Exception), triggering `DB::rollBack()` and a 422 response. Good.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/SerialTrackingTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/OrderController.php tests/Feature/SerialTrackingTest.php
git commit -m "feat(serials): allocate serials on order create with derived quantity"
```

---

## Task 5: OrderController@update — release then reallocate

**Files:**
- Modify: `app/Http/Controllers/OrderController.php:618-749`
- Test: `tests/Feature/SerialTrackingTest.php`

**Interfaces:**
- Consumes: `ProductSerialService::release()`, `allocate()`.
- Produces: `update` accepts `items.*.serials`; releases all of the order's serials before recreating items, then re-allocates the submitted serials for tracked products.

- [ ] **Step 1: Write the failing test**

```php
it('releases old serials and allocates new ones when an order is edited', function () {
    makeShopSettings();
    $user = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true, 'price' => 100, 'cost_price' => 0]);
    app(ProductSerialService::class)->addSerials($product, ['SN-1', 'SN-2', 'SN-3']);

    // Create with SN-1, SN-2
    $this->actingAs($user)->postJson(route('orders.store'), [
        'items' => [['id' => $product->id, 'quantity' => 2, 'price' => 100, 'serials' => ['SN-1', 'SN-2']]],
        'subtotal' => 200, 'tax' => 0, 'delivery_cost' => 0, 'total' => 200,
        'paid_amount' => 200, 'due_amount' => 0, 'change_amount' => 0, 'discount' => 0,
        'payment_method' => 'cash', 'delivery_method' => 'pickup',
    ])->assertJson(['success' => true]);

    $order = \App\Models\Order::latest('id')->first();

    // Edit to SN-3 only
    $this->actingAs($user)->put(route('orders.update', $order), [
        'items' => [[
            'product_id' => $product->id, 'product_name' => $product->name,
            'quantity' => 1, 'price' => 100, 'total' => 100, 'serials' => ['SN-3'],
        ]],
        'payment_method' => 'cash', 'delivery_method' => 'pickup',
        'delivery_cost' => 0, 'paid_amount' => 100, 'due_amount' => 0,
        'change_amount' => 0, 'discount' => 0,
    ])->assertRedirect();

    $product->refresh();
    expect($product->stock)->toBe(2); // SN-1, SN-2 back to available
    expect($product->serials()->where('serial_number', 'SN-3')->first()->status)->toBe('sold');
    expect($product->serials()->where('status', 'sold')->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/SerialTrackingTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement release + reallocate in `update`**

In `update` validation (line 620-637) add:

```php
            'items.*.serials' => 'nullable|array',
            'items.*.serials.*' => 'string',
```

Replace the stock-restore + recreate block (lines 718-749). Release serials by order id before deleting items, and re-allocate for tracked products:

```php
            $service = app(ProductSerialService::class);

            // Release this order's serials back to the pool, and restore
            // aggregate stock for untracked items, before deleting items.
            $service->release($order->id);
            foreach ($order->items as $oldItem) {
                if ($oldItem->product && ! $oldItem->product->serial_tracked) {
                    $oldItem->product->increment('stock', $oldItem->quantity);
                }
            }
            $order->items()->delete();

            foreach ($validated['items'] as $item) {
                $product = $item['product_id'] ? Product::find($item['product_id']) : null;
                $costPrice = $product ? $product->cost_price : 0;

                if ($product && $product->serial_tracked) {
                    $serials = array_values(array_unique(array_map('trim', $item['serials'] ?? [])));
                    $quantity = count($serials);

                    if ($quantity < 1) {
                        throw new \Exception("No serial numbers selected for product: {$product->name}");
                    }

                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'],
                        'quantity' => $quantity,
                        'price' => $item['price'],
                        'cost_price' => $costPrice,
                        'total' => $item['price'] * $quantity,
                        'profit' => ($item['price'] - $costPrice) * $quantity,
                        'remark' => $item['remark'] ?? null,
                    ]);

                    $service->allocate($orderItem, $product, $serials);

                    continue;
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'cost_price' => $costPrice,
                    'total' => $item['total'],
                    'profit' => ($item['price'] - $costPrice) * $item['quantity'],
                    'remark' => $item['remark'] ?? null,
                ]);

                if ($product) {
                    $product->decrement('stock', $item['quantity']);
                }
            }
```

Note: the order header totals (`subtotal`, `tax`, `total` computed from `collect($validated['items'])->sum('total')` at lines 673-678) are unaffected — the client still sends `total` per item. For tracked items the client's `total` should already reflect `price × serial count` (frontend keeps them in sync, Task 10).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/SerialTrackingTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/OrderController.php tests/Feature/SerialTrackingTest.php
git commit -m "feat(serials): release and reallocate serials on order edit"
```

---

## Task 6: Release serials on void/cancel

**Files:**
- Modify: `app/Http/Controllers/OrderController.php:772-842`
- Test: `tests/Feature/SerialTrackingTest.php`

**Interfaces:**
- Consumes: `ProductSerialService::release()`.
- Produces: `destroy` (delete branch) and `updateStatus` (→cancelled) release the order's serials.

- [ ] **Step 1: Write the failing tests**

```php
it('returns serials to the pool when an order is deleted', function () {
    makeShopSettings();
    $user = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true, 'price' => 100]);
    app(ProductSerialService::class)->addSerials($product, ['SN-1', 'SN-2']);

    $this->actingAs($user)->postJson(route('orders.store'), [
        'items' => [['id' => $product->id, 'quantity' => 2, 'price' => 100, 'serials' => ['SN-1', 'SN-2']]],
        'subtotal' => 200, 'tax' => 0, 'delivery_cost' => 0, 'total' => 200,
        'paid_amount' => 200, 'due_amount' => 0, 'change_amount' => 0, 'discount' => 0,
        'payment_method' => 'cash', 'delivery_method' => 'pickup',
    ])->assertJson(['success' => true]);
    $order = \App\Models\Order::latest('id')->first();

    $this->actingAs($user)->delete(route('orders.destroy', $order), [
        'deletion_reason' => 'Customer changed mind',
    ])->assertRedirect();

    expect($product->fresh()->stock)->toBe(2);
    expect($product->serials()->where('status', 'sold')->count())->toBe(0);
});

it('returns serials to the pool when an order is cancelled via status update', function () {
    makeShopSettings();
    $user = serialAdmin();
    $product = Product::factory()->create(['serial_tracked' => true, 'price' => 100]);
    app(ProductSerialService::class)->addSerials($product, ['SN-1']);

    $this->actingAs($user)->postJson(route('orders.store'), [
        'items' => [['id' => $product->id, 'quantity' => 1, 'price' => 100, 'serials' => ['SN-1']]],
        'subtotal' => 100, 'tax' => 0, 'delivery_cost' => 0, 'total' => 100,
        'paid_amount' => 100, 'due_amount' => 0, 'change_amount' => 0, 'discount' => 0,
        'payment_method' => 'cash', 'delivery_method' => 'pickup',
    ])->assertJson(['success' => true]);
    $order = \App\Models\Order::latest('id')->first();

    $this->actingAs($user)->put(route('orders.updateStatus', $order), ['status' => 'cancelled'])
        ->assertRedirect();

    expect($product->fresh()->stock)->toBe(1);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/SerialTrackingTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement release on void/cancel**

In `destroy`, delete branch — before `$order->delete()` (line 841), add serial release alongside the existing stock-restore loop (lines 834-838). Change that loop to skip tracked products and add a release call:

```php
                // Restore product stock (untracked) and release serials (tracked)
                app(ProductSerialService::class)->release($order->id);
                foreach ($order->items as $item) {
                    if ($item->product && ! $item->product->serial_tracked) {
                        $item->product->increment('stock', $item->quantity);
                    }
                }

                // Delete the order (this will also delete order items due to cascade)
                $order->delete();
```

In `updateStatus` (line 772-781), release serials when transitioning to cancelled:

```php
    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled',
        ]);

        if ($validated['status'] === 'cancelled' && $order->status !== 'cancelled') {
            app(ProductSerialService::class)->release($order->id);
            foreach ($order->items as $item) {
                if ($item->product && ! $item->product->serial_tracked) {
                    $item->product->increment('stock', $item->quantity);
                }
            }
        }

        $order->update(['status' => $validated['status'], 'payment_status' => $validated['status']]);

        return back()->with('success', 'Order status updated successfully');
    }
```

Note: the MyInvois-cancel branch in `destroy` (lines 809-812) keeps the order as `cancelled` rather than deleting; add `app(ProductSerialService::class)->release($order->id);` there too, right after the `$order->update([... 'status' => 'cancelled' ...])` call, so post-72h cancellations also free serials.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/SerialTrackingTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/OrderController.php tests/Feature/SerialTrackingTest.php
git commit -m "feat(serials): release serials on order void and cancellation"
```

---

## Task 7: Full backend regression run

**Files:** none (verification only)

- [ ] **Step 1: Run the full suite**

Run: `php artisan test`
Expected: PASS — all existing tests plus the new `SerialTrackingTest.php`. Pay attention to any order/report tests that assumed direct stock mutation.

- [ ] **Step 2: Run Pint**

Run: `./vendor/bin/pint`
Expected: clean / auto-fixed.

- [ ] **Step 3: Commit any Pint fixes**

```bash
git add -A && git commit -m "style: pint fixes for serial tracking" || echo "nothing to format"
```

---

## Task 8: Frontend — product form `serial_tracked` toggle

**Files:**
- Modify: `resources/js/pages/Products/Create.vue`
- Modify: `resources/js/pages/Products/Edit.vue`

**Interfaces:**
- Consumes: `products.store` / `products.update` now accept `serial_tracked`.
- Produces: a checkbox that sets `form.serial_tracked`; when checked, the Stock input is hidden (Create) / read-only (Edit).

Frontend components have no automated tests here — verify manually in the browser.

- [ ] **Step 1: Add the field to `Create.vue`**

In the `useForm({...})` call (around line 202-213) add `serial_tracked: false,`. Add a checkbox near the Stock field, and hide/disable Stock when checked:

```vue
<div class="flex items-center gap-2">
  <input id="serial_tracked" type="checkbox" v-model="form.serial_tracked" class="rounded border-gray-300" />
  <label for="serial_tracked" class="text-sm font-medium">Track serial numbers</label>
</div>
<p v-if="form.serial_tracked" class="text-xs text-muted-foreground">
  Stock is managed by adding serial numbers on the product page after saving.
</p>
```

Wrap the existing Stock input with `v-if="!form.serial_tracked"` so it's hidden for tracked products (the backend forces `stock = 0`).

- [ ] **Step 2: Add the field to `Edit.vue`**

In `useForm({...})` (around line 212-224) add `serial_tracked: props.product.serial_tracked ?? false,`. Add the same checkbox. For Edit, make the Stock input read-only when `serial_tracked` (stock is serial-derived):

```vue
<input ... :readonly="form.serial_tracked" :class="{ 'bg-muted': form.serial_tracked }" />
```

Add a hint that stock for tracked products is managed via serials on the product page.

- [ ] **Step 3: Manual verification**

Run: `composer dev`, then in the browser: create a product with "Track serial numbers" checked → confirm it saves with stock 0 and no error. Edit an untracked product with stock > 0, tick the box, save → expect the "Reduce stock to zero" error. Set stock 0 first, then enabling works.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/Products/Create.vue resources/js/pages/Products/Edit.vue
git commit -m "feat(serials): serial_tracked toggle on product create/edit forms"
```

---

## Task 9: Frontend — serial management panel on Products/Show

**Files:**
- Modify: `resources/js/pages/Products/Show.vue:77-136`

**Interfaces:**
- Consumes: `product.serial_tracked`, `product.serials` (eager-loaded available serials from `show`), routes `products.serials.store` / `products.serials.destroy`.
- Produces: bulk add (paste/scan) + list + remove UI, shown instead of the restock/withdraw form for tracked products.

- [ ] **Step 1: Conditionally render the panel**

Wrap the existing Stock Management block (restock/withdraw) with `v-if="!product.serial_tracked"`. Add a sibling block `v-else` containing the serial panel:

```vue
<div v-else class="rounded-lg border p-4">
  <h3 class="mb-2 font-semibold">Serial Numbers ({{ product.serials?.length ?? 0 }} available)</h3>

  <form @submit.prevent="addSerials" class="space-y-2">
    <textarea
      v-model="serialInput"
      rows="4"
      placeholder="Paste or scan one serial per line"
      class="w-full rounded border p-2 font-mono text-sm"
      @keydown.enter.exact.prevent="captureScan"
    ></textarea>
    <button type="submit" class="rounded bg-primary px-3 py-1.5 text-sm text-primary-foreground">Add Serials</button>
  </form>

  <ul class="mt-3 divide-y">
    <li v-for="s in product.serials" :key="s.id" class="flex items-center justify-between py-1 font-mono text-sm">
      <span>{{ s.serial_number }}</span>
      <button type="button" class="text-destructive" @click="removeSerial(s)">×</button>
    </li>
  </ul>
</div>
```

- [ ] **Step 2: Add the script logic**

In the `<script setup>`:

```ts
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const serialInput = ref('');

// Scan guns usually end with Enter — keep the line and add a newline for the next scan.
function captureScan(e: KeyboardEvent) {
  if (!serialInput.value.endsWith('\n')) serialInput.value += '\n';
}

const serialForm = useForm({ serials: [] as string[] });

function addSerials() {
  const serials = serialInput.value
    .split('\n')
    .map((s) => s.trim())
    .filter(Boolean);
  if (!serials.length) return;
  serialForm.serials = serials;
  serialForm.post(route('products.serials.store', props.product.id), {
    preserveScroll: true,
    onSuccess: () => { serialInput.value = ''; serialForm.reset(); },
  });
}

function removeSerial(serial: { id: number }) {
  router.delete(route('products.serials.destroy', [props.product.id, serial.id]), { preserveScroll: true });
}
```

(Match the existing `props`/`route` usage already present in `Show.vue`.)

- [ ] **Step 3: Manual verification**

Run: `composer dev`. Open a tracked product's Show page. Paste 3 serials → Add → list shows 3, header count 3, product stock reads 3. Remove one → count 2. Try adding a duplicate of an existing serial → validation error shown.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/Products/Show.vue
git commit -m "feat(serials): serial management panel on product show page"
```

---

## Task 10: Frontend — serial picker on Orders/Create and Orders/Edit

**Files:**
- Modify: `resources/js/pages/Orders/Create.vue:283-364,425-445`
- Modify: `resources/js/pages/Orders/Edit.vue`
- Modify: `app/Http/Controllers/OrderController.php:612-615` (edit payload)

**Interfaces:**
- Consumes: `getPosProducts` now returns `serial_tracked`; `GET products.serials.index` returns `{ serials: [{id, serial_number}] }`.
- Produces: per-line serial chips; `item.quantity = item.serials.length` (read-only for tracked); `item.serials` included in the save payload.

- [ ] **Step 1: Carry `serial_tracked` onto the line and derive quantity**

In `selectProduct` (around line 350-358), store `serial_tracked` and init `serials`:

```ts
item.serial_tracked = product.serial_tracked;
item.serials = [];
```

Bind the quantity input (line ~141) read-only when tracked, and keep it synced. Add a watch or compute in `updateTotal` so `item.quantity = item.serials.length` for tracked lines before computing totals.

- [ ] **Step 2: Add the serial picker UI per line**

For a tracked line, render a scan input + chips:

```vue
<div v-if="item.serial_tracked" class="mt-1 space-y-1">
  <input
    v-model="item.serialScan"
    placeholder="Scan or type serial, press Enter"
    class="w-full rounded border p-1 text-sm font-mono"
    @keydown.enter.prevent="addSerialToItem(item)"
  />
  <div class="flex flex-wrap gap-1">
    <span v-for="sn in item.serials" :key="sn" class="flex items-center gap-1 rounded bg-muted px-2 py-0.5 text-xs font-mono">
      {{ sn }}
      <button type="button" @click="removeSerialFromItem(item, sn)">×</button>
    </span>
  </div>
</div>
```

Script:

```ts
async function addSerialToItem(item: any) {
  const sn = (item.serialScan || '').trim();
  if (!sn) return;
  if (item.serials.includes(sn)) { item.serialScan = ''; return; }

  // Validate against available serials for this product
  const { data } = await axios.get(route('products.serials.index', item.id));
  const available = data.serials.map((s: any) => s.serial_number);
  if (!available.includes(sn)) {
    alert(`Serial ${sn} is not available for this product.`);
    item.serialScan = '';
    return;
  }
  item.serials.push(sn);
  item.serialScan = '';
  item.quantity = item.serials.length;
  updateTotal(item);
}

function removeSerialFromItem(item: any, sn: string) {
  item.serials = item.serials.filter((s: string) => s !== sn);
  item.quantity = item.serials.length;
  updateTotal(item);
}
```

- [ ] **Step 3: Include serials in the save payload**

In `saveOrder` (around line 425-445), add `serials: item.serial_tracked ? item.serials : undefined` to each item object sent to `orders.store`.

- [ ] **Step 4: Mirror in Orders/Edit.vue and pre-load allocated serials**

In `OrderController@edit` (line 612-614), add `serial_tracked` to the products select and load the order's items with their serials:

```php
            'products' => \App\Models\Product::select('id', 'name', 'price', 'stock', 'serial_tracked')
                ->where('stock', '>', 0)
                ->orWhereHas('serials', fn ($q) => $q->where('status', 'sold')->whereHas('order', fn ($o) => $o->where('id', $order->id)))
                ->get(),
```

Also eager-load `items.product` and the serials currently on this order so the Edit page can pre-populate `item.serials`. Load `$order->load('items')` and attach each item's sold serial numbers (query `ProductSerial::where('order_item_id', $item->id)`), passing them to the page. In `Orders/Edit.vue`, initialize each tracked line's `serials` from that data and reuse the same picker logic from Steps 1-3.

- [ ] **Step 5: Manual verification**

Run: `composer dev`.
1. Create an order, add a tracked product, scan 2 valid serials → quantity auto-shows 2 and is read-only; line total = price × 2. Try a non-existent serial → rejected. Save → order created, product stock drops by 2, those serials show `sold`.
2. Edit that order, remove one serial and add a different available one → save → released serial returns to available, new one goes sold, stock correct.
3. Void the order → both serials return to available.
4. Add an untracked product to the same order → quantity is editable as before.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/Orders/Create.vue resources/js/pages/Orders/Edit.vue app/Http/Controllers/OrderController.php
git commit -m "feat(serials): serial picker with derived quantity on order create/edit"
```

---

## Task 11: Final end-to-end verification

**Files:** none

- [ ] **Step 1: Full backend suite**

Run: `php artisan test`
Expected: all green.

- [ ] **Step 2: Frontend build + lint**

Run: `npm run build && npm run lint`
Expected: builds clean, lint passes.

- [ ] **Step 3: Regression smoke on untracked flow**

In the browser: create, edit, and void an order using only untracked products. Confirm stock behaves exactly as before. Confirm POS product listing and the Sales Register report are unaffected. Confirm a walk-in order still queues for MyInvois.

- [ ] **Step 4: Final commit / PR**

```bash
git add -A && git commit -m "test(serials): end-to-end verification notes" || echo "clean"
```

---

## Self-Review Notes

- **Spec coverage:** opt-in flag (Task 1, 8), bulk serial add/scan (Task 3, 9), order-time pick with derived read-only quantity (Task 4, 10), void/cancel/edit release (Tasks 5, 6, 10), global uniqueness (Task 1 partial index + Task 3 validation), stock sync (Task 2), mixed orders (Task 4), no MyInvois/report regression (Task 7, 11). All covered.
- **SQLite uniqueness caveat:** a composite `unique(serial_number, deleted_at)` does NOT enforce live-row uniqueness in SQLite (NULLs compare distinct), so Task 1 uses a **partial unique index** (`WHERE deleted_at IS NULL`) plus application-level `Rule::unique(...)->whereNull('deleted_at')` in Task 3.
- **Edit link durability:** serials are released by `order_id` (durable) before `order_items` are deleted and recreated (Task 5), so the transient `order_item_id` being destroyed on edit is not a problem.
- **Exception types:** `ProductSerialService::allocate()` throws `\RuntimeException` (extends `\Exception`), caught by existing `catch (\Exception $e)` blocks in `store`/`update` → rollback + error response.

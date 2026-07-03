# Tangent SalesHourly Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Report this POS's sales to the Malaysian **Tangent SalesHourly API v2.5** on an hourly, idempotent, void-aware cadence — inert unless enabled in `.env`, and testable from the CLI.

**Architecture:** An hourly artisan command recomputes 24 hourly sales aggregates per day for the last 7 days from live order data, upserts each hour into a `tangent_sales_hourly` table with a content hash + status, and re-sends (one request = one day = 24 records) only the days that changed or previously failed. Idempotency comes from Tangent's upsert-by-(machineid,date,hour) plus our hash-based skip; voids self-correct because a cancelled order lowers its hour's recomputed totals. Three focused units: a pure `HourlySalesAggregator` (DB→numbers), a `TangentClient` (token + HTTP), and the orchestrating command; plus a model + migration.

**Tech Stack:** Laravel 12, PHP 8.2, Pest, SQLite (`:memory:` in tests), Laravel HTTP client (`Http::fake` in tests), array cache driver.

## Global Constraints

- **Opt-in:** nothing sends unless `config('services.tangent.enabled')` is true AND credentials are configured. `TANGENT_ENABLED` defaults `false`.
- **Exactly 24 records per day** (hours `00`–`23`, zero-filled) per request — Tangent rejects any other count.
- **No nulls:** every numeric/amount field is `0`, never null; amounts serialized as 2-dp strings, counts/hour as plain strings.
- **Void = excluded:** an order counts as a sale only when `deleted_at IS NULL AND status <> 'cancelled'`.
- **GTO = ex-tax net:** `gto = Σ(subtotal − discount)`; tax is exclusive; `delivery_cost` excluded; `Σ(all tenders) == gto`.
- **Timezone:** aggregate by `created_at` in `config('services.tangent.timezone')` (default `Asia/Kuala_Lumpur`). App timezone is already KL, so no drift.
- **Tender map:** `cash→cash`, `card→visa`, `e-wallet→tng`, `online_transfer`/`bank_transfer`/unknown → `others_amount`; `mastercard`/`amex`/`voucher` always `0`.
- **Do NOT touch** the TMS (1Utama) or MyInvois integrations. This is a standalone third integration.
- Follow the existing `config/services.php`, `routes/console.php`, and Pest patterns (helpers `makeShopSettings()`, `User::factory()`; DB-touching tests live in `tests/Feature`).

Reference spec: `docs/superpowers/specs/2026-07-04-tangent-saleshourly-integration-design.md`.

---

### Task 1: Config block, migration & model

**Files:**
- Modify: `config/services.php` (append a `tangent` block after `myinvois`)
- Create: `database/migrations/<timestamp>_create_tangent_sales_hourly_table.php`
- Create: `app/Models/TangentSalesHourly.php`
- Test: `tests/Feature/TangentSalesHourlyModelTest.php`

**Interfaces:**
- Consumes: nothing (foundational).
- Produces:
  - Config keys under `services.tangent`: `enabled` (bool), `base_url`, `username`, `password`, `machine_id`, `batch_id`, `gst_registered` (nullable), `lookback_days` (int), `timezone`.
  - Table `tangent_sales_hourly` with unique `(sale_date, hour)`.
  - `App\Models\TangentSalesHourly` with fillable columns `sale_date, hour, receipt_count, gto, gst, discount, service_charge, no_of_pax, cash, tng, visa, mastercard, amex, voucher, others_amount, gst_registered, payload_hash, status, synced_at, response_status, response_body` and method `toApiRecord(): array` returning the Tangent-shaped record (string values, `machineid`/`batchid` from config).

- [ ] **Step 1: Add the config block**

In `config/services.php`, add after the `'myinvois' => [ ... ],` block (before the closing `];`):

```php
    'tangent' => [
        'enabled' => filter_var(env('TANGENT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'base_url' => env('TANGENT_BASE_URL', 'https://staging.synthesis.bz/posmy/v1/api'),
        'username' => env('TANGENT_USERNAME'),
        'password' => env('TANGENT_PASSWORD'),
        'machine_id' => env('TANGENT_MACHINE_ID'),
        'batch_id' => env('TANGENT_BATCH_ID', '1'),
        'gst_registered' => env('TANGENT_GST_REGISTERED'), // null => derive from shop settings
        'lookback_days' => (int) env('TANGENT_LOOKBACK_DAYS', 7),
        'timezone' => env('TANGENT_TIMEZONE', 'Asia/Kuala_Lumpur'),
    ],
```

- [ ] **Step 2: Generate and fill the migration**

Run: `php artisan make:migration create_tangent_sales_hourly_table`

Replace the generated file's body with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tangent_sales_hourly', function (Blueprint $table) {
            $table->id();
            $table->date('sale_date');
            $table->unsignedTinyInteger('hour'); // 0-23
            $table->unsignedInteger('receipt_count')->default(0);
            $table->decimal('gto', 12, 2)->default(0);
            $table->decimal('gst', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->unsignedInteger('no_of_pax')->default(0);
            $table->decimal('cash', 12, 2)->default(0);
            $table->decimal('tng', 12, 2)->default(0);
            $table->decimal('visa', 12, 2)->default(0);
            $table->decimal('mastercard', 12, 2)->default(0);
            $table->decimal('amex', 12, 2)->default(0);
            $table->decimal('voucher', 12, 2)->default(0);
            $table->decimal('others_amount', 12, 2)->default(0);
            $table->char('gst_registered', 1)->default('N');
            $table->string('payload_hash')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->timestamp('synced_at')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamps();

            $table->unique(['sale_date', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tangent_sales_hourly');
    }
};
```

- [ ] **Step 3: Write the failing model test**

Create `tests/Feature/TangentSalesHourlyModelTest.php`:

```php
<?php

use App\Models\TangentSalesHourly;

it('persists an hourly row and enforces the unique (sale_date, hour) key', function () {
    TangentSalesHourly::create([
        'sale_date' => '2026-07-04',
        'hour' => 10,
        'receipt_count' => 3,
        'gto' => 191.54,
        'gst' => 1.55,
        'discount' => 0,
        'cash' => 191.54,
        'gst_registered' => 'N',
        'status' => 'pending',
    ]);

    expect(TangentSalesHourly::count())->toBe(1);

    expect(fn () => TangentSalesHourly::create([
        'sale_date' => '2026-07-04',
        'hour' => 10,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('formats itself into the Tangent API record shape', function () {
    config([
        'services.tangent.machine_id' => '71000005',
        'services.tangent.batch_id' => '1',
    ]);

    $row = new TangentSalesHourly([
        'sale_date' => '2026-07-04',
        'hour' => 5,
        'receipt_count' => 3,
        'gto' => 191.5,
        'gst' => 1.55,
        'discount' => 0,
        'service_charge' => 0,
        'no_of_pax' => 0,
        'cash' => 100,
        'visa' => 91.5,
        'gst_registered' => 'Y',
    ]);

    $record = $row->toApiRecord();

    expect($record)->toMatchArray([
        'machineid' => '71000005',
        'batchid' => '1',
        'date' => '20260704',
        'hour' => '05',
        'receiptcount' => '3',
        'gto' => '191.50',
        'gst' => '1.55',
        'discount' => '0.00',
        'servicecharge' => '0.00',
        'noofpax' => '0',
        'cash' => '100.00',
        'tng' => '0.00',
        'visa' => '91.50',
        'mastercard' => '0.00',
        'amex' => '0.00',
        'voucher' => '0.00',
        'othersamount' => '0.00',
        'gstregistered' => 'Y',
    ]);
});
```

- [ ] **Step 4: Run the test to verify it fails**

Run: `php artisan test tests/Feature/TangentSalesHourlyModelTest.php`
Expected: FAIL — `Class "App\Models\TangentSalesHourly" not found`.

- [ ] **Step 5: Create the model**

Create `app/Models/TangentSalesHourly.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TangentSalesHourly extends Model
{
    protected $table = 'tangent_sales_hourly';

    protected $fillable = [
        'sale_date', 'hour', 'receipt_count', 'gto', 'gst', 'discount',
        'service_charge', 'no_of_pax', 'cash', 'tng', 'visa', 'mastercard',
        'amex', 'voucher', 'others_amount', 'gst_registered', 'payload_hash',
        'status', 'synced_at', 'response_status', 'response_body',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'hour' => 'integer',
        'receipt_count' => 'integer',
        'no_of_pax' => 'integer',
        'gto' => 'decimal:2',
        'gst' => 'decimal:2',
        'discount' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'cash' => 'decimal:2',
        'tng' => 'decimal:2',
        'visa' => 'decimal:2',
        'mastercard' => 'decimal:2',
        'amex' => 'decimal:2',
        'voucher' => 'decimal:2',
        'others_amount' => 'decimal:2',
        'synced_at' => 'datetime',
        'response_status' => 'integer',
    ];

    /**
     * Build the Tangent SalesHourly record for this hour.
     *
     * @return array<string, string>
     */
    public function toApiRecord(): array
    {
        return [
            'machineid' => (string) config('services.tangent.machine_id'),
            'batchid' => (string) config('services.tangent.batch_id', '1'),
            'date' => $this->sale_date->format('Ymd'),
            'hour' => str_pad((string) $this->hour, 2, '0', STR_PAD_LEFT),
            'receiptcount' => (string) (int) $this->receipt_count,
            'gto' => $this->money($this->gto),
            'gst' => $this->money($this->gst),
            'discount' => $this->money($this->discount),
            'servicecharge' => $this->money($this->service_charge),
            'noofpax' => (string) (int) $this->no_of_pax,
            'cash' => $this->money($this->cash),
            'tng' => $this->money($this->tng),
            'visa' => $this->money($this->visa),
            'mastercard' => $this->money($this->mastercard),
            'amex' => $this->money($this->amex),
            'voucher' => $this->money($this->voucher),
            'othersamount' => $this->money($this->others_amount),
            'gstregistered' => $this->gst_registered ?: 'N',
        ];
    }

    private function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test tests/Feature/TangentSalesHourlyModelTest.php`
Expected: PASS (2 passed).

- [ ] **Step 7: Commit**

```bash
git add config/services.php database/migrations/*_create_tangent_sales_hourly_table.php app/Models/TangentSalesHourly.php tests/Feature/TangentSalesHourlyModelTest.php
git commit -m "feat(tangent): config, tangent_sales_hourly table + model"
```

---

### Task 2: HourlySalesAggregator

**Files:**
- Create: `app/Services/Tangent/HourlySalesAggregator.php`
- Test: `tests/Feature/TangentAggregatorTest.php`

**Interfaces:**
- Consumes: `App\Models\Order`, `App\Models\ShopSettings`, `config('services.tangent.*')`.
- Produces: `HourlySalesAggregator::aggregate(Carbon\CarbonInterface $day): array` returning a 24-element array indexed `0..23`. Each element is an associative array with keys `hour, receipt_count, gto, gst, discount, service_charge, no_of_pax, cash, tng, visa, mastercard, amex, voucher, others_amount, gst_registered` (money as rounded floats, counts as ints, `gst_registered` as `'Y'`/`'N'`). These keys are all `TangentSalesHourly` fillable columns, so the command can `fill()` them directly.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/TangentAggregatorTest.php`:

```php
<?php

use App\Models\Order;
use App\Models\ShopSettings;
use App\Models\User;
use App\Services\Tangent\HourlySalesAggregator;
use Carbon\CarbonImmutable;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.tangent.machine_id' => '71000005',
        'services.tangent.batch_id' => '1',
        'services.tangent.timezone' => 'Asia/Kuala_Lumpur',
        'services.tangent.gst_registered' => 'N',
    ]);
    $this->user = User::factory()->create();
});

function tangentOrderAt(int $userId, string $klDateTime, array $overrides = []): Order
{
    $order = Order::create(array_merge([
        'user_id' => $userId,
        'subtotal' => 100,
        'tax' => 0,
        'delivery_cost' => 0,
        'discount' => 0,
        'total' => 100,
        'profit' => 0,
        'paid_amount' => 100,
        'due_amount' => 0,
        'change_amount' => 0,
        'payment_method' => 'cash',
        'delivery_method' => 'walk-in',
        'status' => 'completed',
    ], $overrides));

    $order->created_at = CarbonImmutable::parse($klDateTime, 'Asia/Kuala_Lumpur');
    $order->save();

    return $order;
}

it('returns exactly 24 zero-filled hours when there are no sales', function () {
    $rows = app(HourlySalesAggregator::class)
        ->aggregate(CarbonImmutable::parse('2026-07-04', 'Asia/Kuala_Lumpur'));

    expect($rows)->toHaveCount(24);
    expect($rows[0]['receipt_count'])->toBe(0);
    expect($rows[23]['gto'])->toBe(0.0);
    expect($rows[10]['gst_registered'])->toBe('N');
});

it('buckets an order into its KL hour with ex-tax GTO', function () {
    tangentOrderAt($this->user->id, '2026-07-04 10:30:00', [
        'subtotal' => 100, 'discount' => 0, 'tax' => 6, 'payment_method' => 'cash',
    ]);

    $rows = app(HourlySalesAggregator::class)
        ->aggregate(CarbonImmutable::parse('2026-07-04', 'Asia/Kuala_Lumpur'));

    expect($rows[10]['receipt_count'])->toBe(1);
    expect($rows[10]['gto'])->toBe(100.0);   // ex-tax net (subtotal - discount)
    expect($rows[10]['gst'])->toBe(6.0);
    expect($rows[10]['cash'])->toBe(100.0);
    expect($rows[9]['receipt_count'])->toBe(0);
});

it('subtracts discount from GTO and the tender', function () {
    tangentOrderAt($this->user->id, '2026-07-04 12:00:00', [
        'subtotal' => 100, 'discount' => 10, 'tax' => 5.4, 'payment_method' => 'card',
    ]);

    $rows = app(HourlySalesAggregator::class)
        ->aggregate(CarbonImmutable::parse('2026-07-04', 'Asia/Kuala_Lumpur'));

    expect($rows[12]['gto'])->toBe(90.0);
    expect($rows[12]['discount'])->toBe(10.0);
    expect($rows[12]['visa'])->toBe(90.0); // card -> visa
});

it('excludes cancelled and soft-deleted orders', function () {
    tangentOrderAt($this->user->id, '2026-07-04 10:00:00', ['subtotal' => 100]);
    tangentOrderAt($this->user->id, '2026-07-04 10:05:00', ['subtotal' => 50, 'status' => 'cancelled']);
    $deleted = tangentOrderAt($this->user->id, '2026-07-04 10:10:00', ['subtotal' => 30]);
    $deleted->delete(); // soft delete

    $rows = app(HourlySalesAggregator::class)
        ->aggregate(CarbonImmutable::parse('2026-07-04', 'Asia/Kuala_Lumpur'));

    expect($rows[10]['receipt_count'])->toBe(1);
    expect($rows[10]['gto'])->toBe(100.0);
});

it('maps payment methods to tender fields and keeps sum(tenders) == gto', function () {
    $map = [
        'cash' => 'cash', 'card' => 'visa', 'e-wallet' => 'tng',
        'online_transfer' => 'others_amount', 'bank_transfer' => 'others_amount',
        'mystery' => 'others_amount',
    ];
    foreach (array_keys($map) as $i => $method) {
        tangentOrderAt($this->user->id, '2026-07-04 10:'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).':00', [
            'subtotal' => 100, 'payment_method' => $method,
        ]);
    }

    $rows = app(HourlySalesAggregator::class)
        ->aggregate(CarbonImmutable::parse('2026-07-04', 'Asia/Kuala_Lumpur'));

    expect($rows[10]['cash'])->toBe(100.0);
    expect($rows[10]['visa'])->toBe(100.0);
    expect($rows[10]['tng'])->toBe(100.0);
    expect($rows[10]['others_amount'])->toBe(300.0); // online_transfer + bank_transfer + mystery
    expect($rows[10]['gto'])->toBe(600.0);

    $tenders = $rows[10]['cash'] + $rows[10]['tng'] + $rows[10]['visa']
        + $rows[10]['mastercard'] + $rows[10]['amex'] + $rows[10]['voucher']
        + $rows[10]['others_amount'];
    expect($tenders)->toBe($rows[10]['gto']);
});

it('derives gstregistered from shop settings when config is null', function () {
    config(['services.tangent.gst_registered' => null]);
    ShopSettings::create([
        'shop_name' => 'Test', 'currency' => 'MYR',
        'tax_percentage' => 6, 'tax_number' => 'SST-123',
    ]);

    $rows = app(HourlySalesAggregator::class)
        ->aggregate(CarbonImmutable::parse('2026-07-04', 'Asia/Kuala_Lumpur'));

    expect($rows[0]['gst_registered'])->toBe('Y');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/TangentAggregatorTest.php`
Expected: FAIL — `Class "App\Services\Tangent\HourlySalesAggregator" not found`.

- [ ] **Step 3: Implement the aggregator**

Create `app/Services/Tangent/HourlySalesAggregator.php`:

```php
<?php

namespace App\Services\Tangent;

use App\Models\Order;
use App\Models\ShopSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class HourlySalesAggregator
{
    /** POS payment_method => internal tender bucket key. */
    private const TENDER_MAP = [
        'cash' => 'cash',
        'card' => 'visa',
        'e-wallet' => 'tng',
        'online_transfer' => 'others_amount',
        'bank_transfer' => 'others_amount',
    ];

    /**
     * Build the 24 hourly aggregate rows for one KL calendar day.
     *
     * @return array<int, array<string, mixed>> indexed 0..23
     */
    public function aggregate(CarbonInterface $day): array
    {
        $tz = config('services.tangent.timezone', 'Asia/Kuala_Lumpur');
        $start = CarbonImmutable::parse($day->format('Y-m-d'), $tz)->startOfDay();
        $endExclusive = $start->addDay();
        $gstRegistered = $this->resolveGstRegistered();

        $buckets = [];
        for ($h = 0; $h < 24; $h++) {
            $buckets[$h] = [
                'receipt_count' => 0,
                'gto' => 0.0, 'gst' => 0.0, 'discount' => 0.0,
                'cash' => 0.0, 'tng' => 0.0, 'visa' => 0.0,
                'mastercard' => 0.0, 'amex' => 0.0, 'voucher' => 0.0,
                'others_amount' => 0.0,
            ];
        }

        $orders = Order::query()
            ->whereNull('deleted_at')
            ->where('status', '<>', 'cancelled')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $endExclusive)
            ->get(['created_at', 'subtotal', 'discount', 'tax', 'payment_method']);

        foreach ($orders as $order) {
            $hour = (int) $order->created_at->copy()->setTimezone($tz)->format('G');
            $net = (float) $order->subtotal - (float) $order->discount; // ex-tax net
            $tender = self::TENDER_MAP[$order->payment_method] ?? 'others_amount';

            $buckets[$hour]['receipt_count'] += 1;
            $buckets[$hour]['gto'] += $net;
            $buckets[$hour]['gst'] += (float) $order->tax;
            $buckets[$hour]['discount'] += (float) $order->discount;
            $buckets[$hour][$tender] += $net;
        }

        $rows = [];
        foreach ($buckets as $h => $b) {
            $rows[$h] = [
                'hour' => $h,
                'receipt_count' => $b['receipt_count'],
                'gto' => round($b['gto'], 2),
                'gst' => round($b['gst'], 2),
                'discount' => round($b['discount'], 2),
                'service_charge' => 0.0,
                'no_of_pax' => 0,
                'cash' => round($b['cash'], 2),
                'tng' => round($b['tng'], 2),
                'visa' => round($b['visa'], 2),
                'mastercard' => round($b['mastercard'], 2),
                'amex' => round($b['amex'], 2),
                'voucher' => round($b['voucher'], 2),
                'others_amount' => round($b['others_amount'], 2),
                'gst_registered' => $gstRegistered,
            ];
        }

        return $rows;
    }

    private function resolveGstRegistered(): string
    {
        $configured = config('services.tangent.gst_registered');
        if ($configured !== null && $configured !== '') {
            return strtoupper((string) $configured) === 'Y' ? 'Y' : 'N';
        }

        $settings = ShopSettings::first();
        if (! $settings) {
            return 'N';
        }

        $registered = ($settings->enable_tax ?? true)
            && filled($settings->tax_number)
            && (float) $settings->tax_percentage > 0;

        return $registered ? 'Y' : 'N';
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/TangentAggregatorTest.php`
Expected: PASS (6 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Tangent/HourlySalesAggregator.php tests/Feature/TangentAggregatorTest.php
git commit -m "feat(tangent): hourly sales aggregator (ex-tax, void-aware, tender map)"
```

---

### Task 3: TangentClient

**Files:**
- Create: `app/Services/Tangent/TangentClient.php`
- Test: `tests/Feature/TangentClientTest.php`

**Interfaces:**
- Consumes: `config('services.tangent.*')`, `Http`, `Cache`, `Log`.
- Produces:
  - `TangentClient::isConfigured(): bool` — base_url + username + password + machine_id all present.
  - `TangentClient::isEnabled(): bool` — `config('services.tangent.enabled')` true AND `isConfigured()`.
  - `TangentClient::token(): ?string` — bearer token, cached for `expires_in − 60s`; `null` on failure.
  - `TangentClient::sendSales(array $records): array` — returns `['ok' => bool, 'status' => int, 'body' => string]`. Wraps records as `{"sales":[{"sale":{...}}, ...]}`, posts with `Authorization: Bearer <token>`. Never throws.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/TangentClientTest.php`:

```php
<?php

use App\Services\Tangent\TangentClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.tangent.enabled' => true,
        'services.tangent.base_url' => 'https://tangent.test/api',
        'services.tangent.username' => 'postestapimy',
        'services.tangent.password' => '@APITest1234',
        'services.tangent.machine_id' => '71000005',
    ]);
});

it('is enabled only when enabled flag and all credentials are present', function () {
    expect(app(TangentClient::class)->isEnabled())->toBeTrue();
    expect(app(TangentClient::class)->isConfigured())->toBeTrue();

    config(['services.tangent.machine_id' => null]);
    expect(app(TangentClient::class)->isConfigured())->toBeFalse();
    expect(app(TangentClient::class)->isEnabled())->toBeFalse();

    config(['services.tangent.machine_id' => '71000005', 'services.tangent.enabled' => false]);
    expect(app(TangentClient::class)->isEnabled())->toBeFalse();
    expect(app(TangentClient::class)->isConfigured())->toBeTrue();
});

it('fetches and caches the bearer token', function () {
    Http::fake(['*/token' => Http::response(['access_token' => 'abc', 'expires_in' => 1799], 200)]);

    $client = app(TangentClient::class);
    expect($client->token())->toBe('abc');
    expect($client->token())->toBe('abc'); // served from cache

    $tokenCalls = collect(Http::recorded())
        ->filter(fn ($pair) => str_contains($pair[0]->url(), '/token'));
    expect($tokenCalls)->toHaveCount(1);
});

it('returns null when the token request fails', function () {
    Http::fake(['*/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    expect(app(TangentClient::class)->token())->toBeNull();
});

it('sends sales wrapped in the sales/sale envelope with a bearer header', function () {
    Http::fake([
        '*/token' => Http::response(['access_token' => 'abc', 'expires_in' => 1799], 200),
        '*/SalesHourly' => Http::response(['status' => 'success', 'message' => 'ok'], 200),
    ]);

    $records = [
        ['machineid' => '71000005', 'hour' => '00'],
        ['machineid' => '71000005', 'hour' => '01'],
    ];

    $result = app(TangentClient::class)->sendSales($records);

    expect($result['ok'])->toBeTrue();
    expect($result['status'])->toBe(200);

    Http::assertSent(fn ($r) => str_contains($r->url(), 'SalesHourly')
        && $r->hasHeader('Authorization', 'Bearer abc')
        && count($r['sales']) === 2
        && $r['sales'][0]['sale']['hour'] === '00');
});

it('reports ok=false on a 500 error without throwing', function () {
    Http::fake([
        '*/token' => Http::response(['access_token' => 'abc', 'expires_in' => 1799], 200),
        '*/SalesHourly' => Http::response(['status' => 'error', 'errors' => [['message' => 'boom']]], 500),
    ]);

    $result = app(TangentClient::class)->sendSales([['hour' => '00']]);

    expect($result['ok'])->toBeFalse();
    expect($result['status'])->toBe(500);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/TangentClientTest.php`
Expected: FAIL — `Class "App\Services\Tangent\TangentClient" not found`.

- [ ] **Step 3: Implement the client**

Create `app/Services/Tangent/TangentClient.php`:

```php
<?php

namespace App\Services\Tangent;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TangentClient
{
    public function isConfigured(): bool
    {
        $c = config('services.tangent');

        return filled($c['base_url'] ?? null)
            && filled($c['username'] ?? null)
            && filled($c['password'] ?? null)
            && filled($c['machine_id'] ?? null);
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.tangent.enabled') && $this->isConfigured();
    }

    /**
     * Fetch (and cache) the Tangent bearer token.
     *
     * The spec is self-contradictory (GET + form body); we implement it per the
     * documented shape and log the exchange so it can be corrected if needed.
     */
    public function token(): ?string
    {
        $base = $this->baseUrl();
        $username = (string) config('services.tangent.username');
        $password = (string) config('services.tangent.password');
        $cacheKey = 'tangent_token_'.md5($base.'|'.$username);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                ->timeout(30)
                ->send('GET', $base.'/token', [
                    'body' => http_build_query([
                        'grant_type' => 'password',
                        'username' => $username,
                        'password' => $password,
                    ]),
                ]);

            Log::info('Tangent token response', ['status' => $response->status()]);

            if (! $response->successful()) {
                Log::error('Tangent token request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $token = $response->json('access_token');
            if (! $token) {
                Log::error('Tangent token missing access_token', ['body' => $response->body()]);

                return null;
            }

            $ttl = max(60, (int) ($response->json('expires_in') ?? 1799) - 60);
            Cache::put($cacheKey, $token, $ttl);

            return $token;
        } catch (\Throwable $e) {
            Log::error('Tangent token exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * POST one day of hourly sale records to Tangent.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array{ok: bool, status: int, body: string}
     */
    public function sendSales(array $records): array
    {
        $token = $this->token();
        if (! $token) {
            return ['ok' => false, 'status' => 0, 'body' => 'Unable to obtain Tangent token'];
        }

        $payload = [
            'sales' => array_map(fn ($r) => ['sale' => $r], array_values($records)),
        ];

        Log::info('Tangent SalesHourly request', ['count' => count($records)]);

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->post($this->baseUrl().'/SalesHourly', $payload);

            Log::info('Tangent SalesHourly response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $ok = $response->successful() && $response->json('status') === 'success';

            return ['ok' => $ok, 'status' => $response->status(), 'body' => $response->body()];
        } catch (\Throwable $e) {
            Log::error('Tangent SalesHourly exception', ['message' => $e->getMessage()]);

            return ['ok' => false, 'status' => 0, 'body' => $e->getMessage()];
        }
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.tangent.base_url'), '/');
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/TangentClientTest.php`
Expected: PASS (5 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Tangent/TangentClient.php tests/Feature/TangentClientTest.php
git commit -m "feat(tangent): API client (token caching + SalesHourly send)"
```

---

### Task 4: PushTangentSalesHourly command

**Files:**
- Create: `app/Console/Commands/PushTangentSalesHourly.php`
- Test: `tests/Feature/TangentPushCommandTest.php`

**Interfaces:**
- Consumes: `HourlySalesAggregator::aggregate()`, `TangentClient::isConfigured()/isEnabled()/token()/sendSales()`, `App\Models\TangentSalesHourly`.
- Produces: artisan command `tangent:push-sales` with options `--date=`, `--dry-run`, `--test-connection`, `--force`. Persists/updates `tangent_sales_hourly` rows and sends per-day 24-record batches.

**Behaviour:**
- Real run (no flags): requires `isEnabled()`; else warns and exits 0 without HTTP.
- `--dry-run` / `--test-connection`: require only `isConfigured()`; work even when `enabled=false`.
- `--dry-run`: prints per-day payload, no DB writes, no HTTP.
- Each real run: recompute 24 hours/day for the lookback window (or `--date`); `firstOrNew` on `(sale_date,hour)`, set fields + `payload_hash = sha1(json_encode($agg))`; if the hash changed, mark the row `pending`. A day is sent if any of its rows is not `sent` (or `--force`). On success mark the day's 24 rows `sent` + `synced_at`; on failure mark `failed`. Both store `response_status`/`response_body`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/TangentPushCommandTest.php`:

```php
<?php

use App\Models\Order;
use App\Models\TangentSalesHourly;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.tangent.enabled' => true,
        'services.tangent.base_url' => 'https://tangent.test/api',
        'services.tangent.username' => 'u',
        'services.tangent.password' => 'p',
        'services.tangent.machine_id' => '71000005',
        'services.tangent.batch_id' => '1',
        'services.tangent.gst_registered' => 'N',
        'services.tangent.timezone' => 'Asia/Kuala_Lumpur',
    ]);
    $this->user = User::factory()->create();
});

function pushOrderAt(int $userId, string $klDateTime, array $overrides = []): Order
{
    $order = Order::create(array_merge([
        'user_id' => $userId, 'subtotal' => 100, 'tax' => 0, 'delivery_cost' => 0,
        'discount' => 0, 'total' => 100, 'profit' => 0, 'paid_amount' => 100,
        'due_amount' => 0, 'change_amount' => 0, 'payment_method' => 'cash',
        'delivery_method' => 'walk-in', 'status' => 'completed',
    ], $overrides));
    $order->created_at = CarbonImmutable::parse($klDateTime, 'Asia/Kuala_Lumpur');
    $order->save();

    return $order;
}

function fakeTangentSuccess(): void
{
    Http::fake([
        '*/token' => Http::response(['access_token' => 'abc', 'expires_in' => 1799], 200),
        '*/SalesHourly' => Http::response(['status' => 'success', 'message' => 'ok'], 200),
    ]);
}

function salesRequestCount(): int
{
    return collect(Http::recorded())
        ->filter(fn ($pair) => str_contains($pair[0]->url(), 'SalesHourly'))
        ->count();
}

it('does nothing and makes no HTTP call when disabled', function () {
    config(['services.tangent.enabled' => false]);
    Http::fake();
    pushOrderAt($this->user->id, '2026-07-04 10:00:00');

    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04'])->assertSuccessful();

    expect(TangentSalesHourly::count())->toBe(0);
    Http::assertNothingSent();
});

it('sends a day once and marks its 24 rows sent', function () {
    fakeTangentSuccess();
    pushOrderAt($this->user->id, '2026-07-04 10:00:00', ['subtotal' => 100, 'payment_method' => 'cash']);

    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04'])->assertSuccessful();

    $rows = TangentSalesHourly::where('sale_date', '2026-07-04')->get();
    expect($rows)->toHaveCount(24);
    expect($rows->firstWhere('hour', 10)->status)->toBe('sent');
    expect($rows->firstWhere('hour', 10)->receipt_count)->toBe(1);
    expect((float) $rows->firstWhere('hour', 10)->gto)->toBe(100.0);
    expect(salesRequestCount())->toBe(1);

    Http::assertSent(fn ($r) => str_contains($r->url(), 'SalesHourly') && count($r['sales']) === 24);
});

it('is idempotent: an unchanged day is not re-sent', function () {
    fakeTangentSuccess();
    pushOrderAt($this->user->id, '2026-07-04 10:00:00');

    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04'])->assertSuccessful();
    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04'])->assertSuccessful();

    expect(salesRequestCount())->toBe(1); // second run skipped
});

it('re-sends a day after an order is voided and reflects the deduction', function () {
    fakeTangentSuccess();
    $order = pushOrderAt($this->user->id, '2026-07-04 10:00:00', ['subtotal' => 100]);

    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04'])->assertSuccessful();

    $order->update(['status' => 'cancelled']);
    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04'])->assertSuccessful();

    expect(salesRequestCount())->toBe(2); // resent after void
    $row = TangentSalesHourly::where('sale_date', '2026-07-04')->where('hour', 10)->first();
    expect($row->receipt_count)->toBe(0);
    expect((float) $row->gto)->toBe(0.0);
    expect($row->status)->toBe('sent');
});

it('marks a day failed on error, then retries and succeeds next run', function () {
    Http::fake([
        '*/token' => Http::response(['access_token' => 'abc', 'expires_in' => 1799], 200),
        '*/SalesHourly' => Http::sequence()
            ->push(['status' => 'error', 'errors' => [['message' => 'boom']]], 500)
            ->push(['status' => 'success', 'message' => 'ok'], 200),
    ]);
    pushOrderAt($this->user->id, '2026-07-04 10:00:00');

    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04'])->assertSuccessful();
    expect(TangentSalesHourly::where('sale_date', '2026-07-04')->where('hour', 10)->first()->status)->toBe('failed');

    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04'])->assertSuccessful();
    expect(TangentSalesHourly::where('sale_date', '2026-07-04')->where('hour', 10)->first()->status)->toBe('sent');
});

it('dry-run writes nothing and sends nothing', function () {
    Http::fake();
    pushOrderAt($this->user->id, '2026-07-04 10:00:00');

    $this->artisan('tangent:push-sales', ['--date' => '2026-07-04', '--dry-run' => true])->assertSuccessful();

    expect(TangentSalesHourly::count())->toBe(0);
    Http::assertNothingSent();
});

it('test-connection fetches a token and sends no sales', function () {
    fakeTangentSuccess();

    $this->artisan('tangent:push-sales', ['--test-connection' => true])->assertSuccessful();

    expect(salesRequestCount())->toBe(0);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/TangentPushCommandTest.php`
Expected: FAIL — command `tangent:push-sales` is not defined.

- [ ] **Step 3: Implement the command**

Create `app/Console/Commands/PushTangentSalesHourly.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\TangentSalesHourly;
use App\Services\Tangent\HourlySalesAggregator;
use App\Services\Tangent\TangentClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PushTangentSalesHourly extends Command
{
    protected $signature = 'tangent:push-sales
        {--date= : Only process this KL date (YYYY-MM-DD)}
        {--dry-run : Compute and print payloads without sending or writing}
        {--test-connection : Fetch a token and report, without sending sales}
        {--force : Re-send every day in the window regardless of stored status}';

    protected $description = 'Aggregate hourly sales and push them to the Tangent SalesHourly API';

    public function handle(HourlySalesAggregator $aggregator, TangentClient $client): int
    {
        $tz = config('services.tangent.timezone', 'Asia/Kuala_Lumpur');

        if ($this->option('test-connection')) {
            if (! $client->isConfigured()) {
                return $this->bailNotConfigured();
            }

            return $this->testConnection($client);
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            if (! $client->isConfigured()) {
                return $this->bailNotConfigured();
            }
        } elseif (! $client->isEnabled()) {
            $this->warn('Tangent integration is disabled or not configured. Set TANGENT_ENABLED=true to send.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $sent = $skipped = $failed = 0;

        foreach ($this->resolveDays($tz) as $day) {
            $records = $aggregator->aggregate($day);

            if ($dryRun) {
                $this->line("== {$day->format('Y-m-d')} ==");
                $this->line(json_encode(
                    ['sales' => array_map(fn ($r) => ['sale' => $this->previewRecord($day, $r)], $records)],
                    JSON_PRETTY_PRINT
                ));

                continue;
            }

            $dateKey = $day->format('Y-m-d');
            $dayNeedsSend = $force;

            foreach ($records as $hour => $agg) {
                $hash = sha1(json_encode($agg));
                $row = TangentSalesHourly::firstOrNew(['sale_date' => $dateKey, 'hour' => $hour]);
                $changed = ! $row->exists || $row->payload_hash !== $hash;

                $row->fill($agg);
                $row->sale_date = $dateKey;
                $row->hour = $hour;
                $row->payload_hash = $hash;
                if ($changed) {
                    $row->status = 'pending';
                }
                $row->save();

                if ($row->status !== 'sent') {
                    $dayNeedsSend = true;
                }
            }

            if (! $dayNeedsSend) {
                $skipped++;

                continue;
            }

            $rows = TangentSalesHourly::where('sale_date', $dateKey)->orderBy('hour')->get();
            $result = $client->sendSales($rows->map->toApiRecord()->all());

            $update = [
                'response_status' => $result['status'],
                'response_body' => Str::limit((string) $result['body'], 1000),
            ];

            if ($result['ok']) {
                $update['status'] = 'sent';
                $update['synced_at'] = now();
                $sent++;
                $this->info("Sent {$dateKey} (24 records).");
            } else {
                $update['status'] = 'failed';
                $failed++;
                $this->error("Failed {$dateKey}: {$result['body']}");
            }

            TangentSalesHourly::where('sale_date', $dateKey)->update($update);
        }

        if (! $dryRun) {
            $this->info("Done. Sent: {$sent}, skipped: {$skipped}, failed: {$failed}.");
        }

        return self::SUCCESS;
    }

    /** @return array<int, CarbonImmutable> */
    private function resolveDays(string $tz): array
    {
        if ($date = $this->option('date')) {
            return [CarbonImmutable::parse($date, $tz)->startOfDay()];
        }

        $today = CarbonImmutable::now($tz)->startOfDay();
        $lookback = max(1, (int) config('services.tangent.lookback_days', 7));

        $days = [];
        for ($i = $lookback - 1; $i >= 0; $i--) {
            $days[] = $today->subDays($i);
        }

        return $days;
    }

    private function testConnection(TangentClient $client): int
    {
        if ($token = $client->token()) {
            $this->info('Tangent token obtained successfully (length '.strlen($token).').');

            return self::SUCCESS;
        }

        $this->error('Failed to obtain a Tangent token. Check credentials and logs.');

        return self::FAILURE;
    }

    /** @param array<string, mixed> $agg */
    private function previewRecord(CarbonImmutable $day, array $agg): array
    {
        return (new TangentSalesHourly(array_merge($agg, ['sale_date' => $day->format('Y-m-d')])))
            ->toApiRecord();
    }

    private function bailNotConfigured(): int
    {
        $this->error('Tangent is not configured (need base_url, username, password, machine_id).');

        return self::FAILURE;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/TangentPushCommandTest.php`
Expected: PASS (8 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/PushTangentSalesHourly.php tests/Feature/TangentPushCommandTest.php
git commit -m "feat(tangent): tangent:push-sales command (idempotent, void-aware, dry-run)"
```

---

### Task 5: Schedule registration & documentation

**Files:**
- Modify: `routes/console.php` (add the hourly schedule line)
- Modify: `.env.example` (append Tangent block)
- Modify: `CLAUDE.md` (env vars + integration flow note + command list)
- Test: `tests/Feature/TangentScheduleTest.php`

**Interfaces:**
- Consumes: the `tangent:push-sales` command from Task 4.
- Produces: an hourly KL-timezone schedule entry; documented env vars.

- [ ] **Step 1: Write the failing schedule test**

Create `tests/Feature/TangentScheduleTest.php`:

```php
<?php

use Illuminate\Console\Scheduling\Schedule;

it('schedules tangent:push-sales hourly', function () {
    $events = app(Schedule::class)->events();

    $found = collect($events)->contains(
        fn ($event) => str_contains((string) $event->command, 'tangent:push-sales')
    );

    expect($found)->toBeTrue();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/TangentScheduleTest.php`
Expected: FAIL — `Failed asserting that false is true` (not yet scheduled).

- [ ] **Step 3: Add the schedule line**

In `routes/console.php`, add after the `myinvois:push-queue` line:

```php
Schedule::command('tangent:push-sales')->hourly()->timezone('Asia/Kuala_Lumpur');
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/TangentScheduleTest.php`
Expected: PASS (1 passed).

- [ ] **Step 5: Document env vars in `.env.example`**

Append to `.env.example`:

```dotenv

# Tangent SalesHourly (Malaysia mall sales reporting)
# Inert until TANGENT_ENABLED=true. Credentials/URL provided by the mall vendor.
TANGENT_ENABLED=false
TANGENT_BASE_URL=https://staging.synthesis.bz/posmy/v1/api
TANGENT_USERNAME=
TANGENT_PASSWORD=
TANGENT_MACHINE_ID=
TANGENT_BATCH_ID=1
# TANGENT_GST_REGISTERED=  # Y or N; leave blank to derive from shop settings
TANGENT_LOOKBACK_DAYS=7
TANGENT_TIMEZONE=Asia/Kuala_Lumpur
```

- [ ] **Step 6: Document in `CLAUDE.md`**

In `CLAUDE.md`, add to the "Other Commands" section:

```bash
# Push hourly sales to Tangent (scheduled hourly; idempotent, void-aware)
php artisan tangent:push-sales

# Preview a day's payload without sending, or test the connection
php artisan tangent:push-sales --date=2026-07-04 --dry-run
php artisan tangent:push-sales --test-connection
```

And add to the "Environment Configuration" env block:

```bash
# Tangent SalesHourly (Malaysia)
TANGENT_ENABLED=true|false
TANGENT_BASE_URL=https://staging.synthesis.bz/posmy/v1/api
TANGENT_USERNAME=your_username
TANGENT_PASSWORD=your_password
TANGENT_MACHINE_ID=71000005
TANGENT_BATCH_ID=1
TANGENT_GST_REGISTERED=Y|N   # blank => derive from shop settings
TANGENT_LOOKBACK_DAYS=7
TANGENT_TIMEZONE=Asia/Kuala_Lumpur
```

Add a short subsection under "Important Patterns" (mirroring the MyInvois flow note):

```markdown
### Tangent SalesHourly Flow (Malaysia mall reporting)
1. Enabled only when `TANGENT_ENABLED=true` and credentials are set.
2. `tangent:push-sales` runs hourly. It recomputes 24 hourly aggregates/day for the last
   `TANGENT_LOOKBACK_DAYS` days from live orders (KL timezone), excluding cancelled/soft-deleted
   orders (net-of-void), and upserts them into `tangent_sales_hourly` with a content hash.
3. A day is (re)sent — one request of exactly 24 records — only when its hash changed or a
   prior send failed; unchanged days are skipped. Tangent upserts by (machineid, date, hour),
   so re-sends and later voids self-correct.
4. Tender mapping: cash→cash, card→visa, e-wallet→tng, transfers/unknown→othersamount.
5. Test locally with `--dry-run`, `--date=YYYY-MM-DD`, `--test-connection`, `--force`.
```

- [ ] **Step 7: Run the full test suite**

Run: `php artisan test`
Expected: PASS — all prior tests plus the new Tangent tests green.

- [ ] **Step 8: Run Pint on the new files**

Run: `./vendor/bin/pint app/Services/Tangent app/Console/Commands/PushTangentSalesHourly.php app/Models/TangentSalesHourly.php`
Expected: no style errors (files formatted).

- [ ] **Step 9: Commit**

```bash
git add routes/console.php .env.example CLAUDE.md tests/Feature/TangentScheduleTest.php
git commit -m "feat(tangent): hourly schedule + docs/env for SalesHourly integration"
```

---

## Self-Review

**Spec coverage:**
- Opt-in `.env` gate → Task 1 config (`enabled`), Task 3 `isEnabled()`, Task 4 disabled-path test. ✔
- 24 records/day, zero-filled → Task 2 (24-bucket init), Task 4 "24 rows" test. ✔
- No nulls / string formatting → Task 1 `toApiRecord()` test. ✔
- Void exclusion / deduction → Task 2 exclusion test, Task 4 void-resend test. ✔
- GTO ex-tax, delivery excluded, Σtenders==gto → Task 2 tests. ✔
- Tender map → Task 2 mapping test. ✔
- Idempotency (hash skip, retry) → Task 4 idempotent + failed-retry tests. ✔
- Token + caching + bearer send → Task 3 tests. ✔
- Hourly schedule → Task 5 test. ✔
- CLI testing surface (`--dry-run`/`--date`/`--test-connection`/`--force`) → Task 4 dry-run + test-connection tests; `--force`/`--date` exercised via `--date`. ✔
- Docs/env (.env.example, CLAUDE.md) → Task 5. ✔

**Placeholder scan:** No TBD/TODO; every code + test block is complete.

**Type consistency:** Aggregator output keys == `TangentSalesHourly` fillable columns (used by `fill()` in Task 4). `sendSales()` return shape `['ok','status','body']` consumed consistently in Task 4. `toApiRecord()` produced in Task 1, consumed in Tasks 4. `isEnabled()`/`isConfigured()` defined in Task 3, consumed in Task 4. Consistent.

**One known spec ambiguity (documented, not a gap):** the token endpoint's HTTP method/content-type is contradictory in the vendor PDF; implemented per the documented shape (GET + form body) with full request/response logging so it can be adjusted on first live test.

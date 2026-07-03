# Tangent SalesHourly Integration — Design

**Date:** 2026-07-04
**Status:** Approved (design), pending implementation plan
**Author:** aliifz + Claude

## Purpose

Integrate this POS with the **Tangent SalesHourly API v2.5 (Malaysia)** so that the shop's
sales are reported to the mall's Tangent system on an **hourly** cadence, one record per hour
of the day. The integration must be:

- **Opt-in via `.env`** — completely inert unless `TANGENT_ENABLED=true`.
- **Idempotent** — re-running the job never double-reports; the mall's numbers converge to the
  live order data.
- **Void-aware** — cancelled / voided orders are deducted from the reported net sales.

This is a **new, standalone** integration. It does **not** touch the existing TMS (1Utama)
receipt integration, which is a separate mall and stays as-is. It also does not touch the
MyInvois integration; we only borrow its robust queue-and-status *pattern*.

## The Tangent API (from `Tangent API Documentation POS SalesHourly v2.5 Malaysia.pdf`)

### Authentication — token endpoint

- `GET {base_url}/token`
- Body (form-encoded): `grant_type=password&username=<user>&password=<pass>`
- Success: `{"access_token":"...","token_type":"bearer","expires_in":1799, ...}` (~30 min TTL)
- Failure: `{"error":"invalid_grant","error_description":"The user name or password is incorrect."}`

> **Spec ambiguity:** the doc states `Type: GET` with `content-type: application/json` yet a
> **form-encoded** body — internally inconsistent (this is a standard OAuth2 password grant,
> normally a `POST` with `application/x-www-form-urlencoded`). We implement it **per the doc**
> (GET + form-encoded body) but keep the HTTP method/content-type easy to change, and **log the
> raw request/response** so we can correct it once the real URL/credentials arrive (they are
> sent separately by the vendor).

### Send sales — SalesHourly endpoint

- `POST {base_url}/SalesHourly`
- Header: `Authorization: Bearer <access_token>`, `content-type: application/json`
- Body:

```json
{ "sales": [ { "sale": { "machineid": "71000005", "batchid": "1", "date": "20220125",
  "hour": "00", "receiptcount": "5", "gto": "191.54", "gst": "1.55", "discount": "0.00",
  "servicecharge": "5.00", "noofpax": "0", "cash": "8.97", "tng": "0.00", "visa": "76.78",
  "mastercard": "0.00", "amex": "47.80", "voucher": "0.00", "othersamount": "57.99",
  "gstregistered": "N" } } ] }
```

- Success (200): `{"status":"success","message":"total sales records received: 24. total sales records created in Tangent: 24."}`
- Error (500): `{"status":"error","errors":[{"status_code":"TANGENT_GENERIC_EXCEPTION","message":"..."}]}`

### Field spec

| Field | Type | Notes |
|---|---|---|
| `machineid` | Numeric ≤8 | Provided by Mall Management |
| `batchid` | Numeric | Z-report closing number; examples use `1` |
| `date` | Numeric 8 | `YYYYMMDD` |
| `hour` | Text 2 | `00`–`23` |
| `receiptcount` | Integer | transactions/receipts in the hour, no decimal |
| `gto` | Decimal 2 | **Nett sales after discount, before SST; incl. F&B service charge; after deducting VOID/REFUND** |
| `gst` | Decimal 2 | GST/SST amount |
| `discount` | Decimal 2 | discount amount |
| `servicecharge` | Decimal 2 | F&B only |
| `noofpax` | Integer | F&B only, no decimal |
| `cash`,`tng`,`visa`,`mastercard`,`amex`,`voucher`,`othersamount` | Decimal 2 | tender split, each "after discount and before gst" |
| `gstregistered` | Char 1 | `Y` or `N` |

### Hard rules

1. **Exactly 24 records per day** (hours `00`–`23`), zero-filled when there are no sales.
   A day with ≠24 records is rejected ("Total number of arrays is not tally").
2. **Re-upload the past 7 days** on each run to backfill any gaps.
3. **No nulls** for numeric/amount fields — use `0`.
4. `hour 00` = 00:00–00:59 … `hour 23` = 23:00–23:59.
5. The API **upserts by (machineid, date, hour)** — updates an existing record, creates if
   absent, unless the day is **locked** (then updates silently do not apply).
6. Request limits: ≤128 KB (≤~1000 records) or ≤1 month per request. We send **one day
   (24 records) per request** — unambiguously satisfies rule 1 and gives per-day retry/lock
   granularity.

## How this POS's data maps

Established from the codebase (`app/Models/Order.php`, orders migrations, `OrderController`,
`ShopSettings`):

- **Amounts:** `total = subtotal + tax + delivery_cost − discount`; **tax is exclusive**
  (added on top of `subtotal`). All amounts are `decimal(10,2)`.
- **Void:** an order is voided when `status = 'cancelled'` **or** it is soft-deleted
  (`deleted_at IS NOT NULL`). Both are excluded from aggregates.
- **Tender:** single `orders.payment_method` string (no split payments, no card brand).
  Observed values: `cash`, `card`, `e-wallet`, `online_transfer` (legacy `bank_transfer`).
- **No service charge, no pax** — retail POS; both report as `0`.
- **Tax config:** `shop_settings.tax_percentage`, `enable_tax`, `tax_number` (registration).
  No explicit GST/SST-registered boolean.
- **Timezone:** aggregate by `created_at` converted to `Asia/Kuala_Lumpur`.

### Which orders count as sales

All orders that are **not soft-deleted and not `status='cancelled'`** (i.e. `pending`,
`processing`, `completed`). This matches the existing TMS daily command's behaviour (counts all
non-deleted orders) plus the explicit void exclusion.

### Aggregation per hour

For the set of counted orders whose KL-local `created_at` falls in the hour:

- `receiptcount` = count of those orders
- `gto` = `Σ(subtotal − discount)` (ex-tax net; **delivery_cost excluded** — treated as
  non-merchandise shipping)
- `gst` = `Σ(tax)`
- `discount` = `Σ(discount)`
- `servicecharge` = `0`, `noofpax` = `0`
- Tender fields = `Σ(subtotal − discount)` grouped by mapped tender (see below)
- `gstregistered` = configured/derived `Y`/`N`

Because each order's full ex-tax net (`subtotal − discount`) lands in exactly one tender field
**and** in `gto`, `Σ(all tenders) == gto` holds by construction.

### Tender mapping (best-effort — user decision)

| `orders.payment_method` | Tangent field |
|---|---|
| `cash` | `cash` |
| `card` | `visa` |
| `e-wallet` | `tng` |
| `online_transfer`, `bank_transfer`, anything else/unknown | `othersamount` |

`mastercard`, `amex`, `voucher` are always `0` (no source data). Caveat accepted: `card` lumps
all brands into `visa` because brand is not captured.

### `gstregistered` resolution

If `TANGENT_GST_REGISTERED` is explicitly set (`Y`/`N`), use it. Otherwise derive: `Y` when
`shop_settings.enable_tax` is true **and** `tax_number` is present **and** `tax_percentage > 0`,
else `N`.

### Formatting

All values serialized as **strings** (matching the doc's examples): amounts via
`number_format($v, 2, '.', '')`; integer fields (`receiptcount`, `noofpax`) as plain integer
strings; `hour` as 2-digit; `date` as `YYYYMMDD`; `gstregistered` as `Y`/`N`;
`machineid`/`batchid` as their configured strings.

## Architecture

Three focused units + a model + a command, each independently understandable and testable.

### `App\Services\Tangent\HourlySalesAggregator`

- **Responsibility:** turn live order data into the 24-record array for one day. Pure DB reads;
  no HTTP.
- `aggregate(CarbonImmutable $day): array` → 24 associative arrays (hours 0–23, zero-filled),
  already field-mapped and formatted.
- Handles KL-timezone bucketing, void exclusion, tender mapping, `gstregistered` resolution.
- Depends on: `Order` model, `ShopSettings`, `config('services.tangent')`.

### `App\Services\Tangent\TangentClient`

- **Responsibility:** the HTTP contract with Tangent. Knows nothing about orders.
- `token(): ?string` — fetches (per the doc) and **caches** the bearer token
  (`Cache::remember`, TTL = `expires_in − 60s`, keyed by base_url+username). Returns `null` on
  failure (logged).
- `sendSales(array $records): array` — wraps `records` into `{"sales":[{"sale":...}]}`, POSTs
  with the bearer header, returns `['ok'=>bool,'status'=>int,'body'=>string]`. Never throws;
  catches `\Throwable`, logs request+response like the TMS/MyInvois services.
- `isConfigured(): bool` — base_url + username + password + machine_id present.
- `isEnabled(): bool` — `config('services.tangent.enabled')` is true **and** `isConfigured()`.

### `App\Models\TangentSalesHourly`

- Table `tangent_sales_hourly`; one row per `(sale_date, hour)`, **unique**.
- Columns: `sale_date` (date), `hour` (unsignedTinyInt 0–23), `receipt_count`,
  `gto`, `gst`, `discount`, `service_charge`, `no_of_pax`, `cash`, `tng`, `visa`,
  `mastercard`, `amex`, `voucher`, `others_amount` (decimal 12,2), `gst_registered` (char 1),
  `payload_hash` (string), `status` (enum `pending`/`sent`/`failed`, default `pending`),
  `synced_at` (nullable datetime), `response_status` (nullable int), `response_body`
  (nullable text), timestamps.
- **Not** soft-deleted (operational/audit rows, not domain records).

### `App\Console\Commands\PushTangentSalesHourly` — signature `tangent:push-sales`

Orchestration for each run:

1. If not `TangentClient::isEnabled()` (config `enabled` && configured), log and exit 0.
2. Determine the window: `today` back through `today − (lookback_days − 1)` in KL tz
   (default 7 days incl. today), or the single `--date` day.
3. For each day → `aggregator->aggregate($day)` → for each of the 24 hours,
   `updateOrCreate([sale_date,hour], {...fields, payload_hash})`; if the freshly-computed hash
   differs from the stored one, set `status='pending'`.
4. For each day where any hour row is not `sent` (or `--force`): build the 24-record payload,
   `client->sendSales(...)`. On `ok` → mark that day's 24 rows `status='sent'`,
   `synced_at=now`, store `response_*`. On failure → `status='failed'`, store `response_*`.
5. Summarize (days sent / skipped / failed, records).

**Flags:**

- `--dry-run` — read-only: compute and print the payload(s); **no DB writes, no HTTP**.
- `--date=YYYY-MM-DD` — operate on a single day (for testing/backfill).
- `--test-connection` — fetch a token and report success + expiry, or the error; no sales sent.
- `--force` — send every day in the window regardless of stored status.

### Scheduling

`routes/console.php` (Laravel 12 style, mirroring the MyInvois hourly schedule):

```php
Schedule::command('tangent:push-sales')->hourly()->timezone('Asia/Kuala_Lumpur');
```

The command self-guards on the enabled flag, so scheduling is safe even when disabled.

## Configuration

`config/services.php`:

```php
'tangent' => [
    'enabled'        => env('TANGENT_ENABLED', false),
    'base_url'       => env('TANGENT_BASE_URL', 'https://staging.synthesis.bz/posmy/v1/api'),
    'username'       => env('TANGENT_USERNAME'),
    'password'       => env('TANGENT_PASSWORD'),
    'machine_id'     => env('TANGENT_MACHINE_ID'),
    'batch_id'       => env('TANGENT_BATCH_ID', '1'),
    'gst_registered' => env('TANGENT_GST_REGISTERED'), // null => derive from shop settings
    'lookback_days'  => env('TANGENT_LOOKBACK_DAYS', 7),
    'timezone'       => env('TANGENT_TIMEZONE', 'Asia/Kuala_Lumpur'),
],
```

Documented in `.env.example` and `CLAUDE.md`. `TANGENT_ENABLED` defaults `false` — the feature
is inert until explicitly enabled.

## Idempotency & void handling — summary

- **Re-run safety:** unchanged days have unchanged hashes → `status` stays `sent` → skipped.
- **Void/edit correction:** a cancelled or edited order changes its hour's aggregate → hash
  changes → `status→pending` → day resent → Tangent upserts the corrected 24 records. GTO,
  receiptcount and tender totals all move down accordingly ("deduct VOID").
- **Failure retry:** a failed day stays non-`sent` and is retried on the next hourly run.
- **7-day window:** voids/edits within the last 7 days are always reflected; older days are out
  of the resend window (accepted limit, matching the spec's 7-day backfill guidance).
- **Vendor-side idempotency:** the API's upsert-by-(machineid,date,hour) means even a duplicate
  send is harmless.

## Testing

Pest, TDD (write tests first). Coverage:

- **Aggregator (unit):** KL-timezone hour bucketing (incl. an order near a day boundary);
  void exclusion (`cancelled` and soft-deleted excluded); tender mapping for each
  `payment_method`; ex-tax `gto`; `gst`/`discount` sums; zero-fill to exactly 24 hours;
  `Σ tenders == gto`; `gstregistered` derivation.
- **Command (feature, `Http::fake`):** disabled flag → zero HTTP; a day sends once; unchanged
  day is skipped on re-run; a later void flips the day back to `pending` and resends; a 500
  marks the day `failed` and it retries next run; `--dry-run` performs no writes/HTTP;
  `--date`/`--force` behave as specified.
- **Client (unit, `Http::fake`):** token cached (second call makes no HTTP); bearer header on
  send; correct `{"sales":[{"sale":...}]}` envelope; failure returns `ok=false` without
  throwing.

## Out of scope

- No admin Vue UI (testing is artisan-only, per decision).
- No changes to TMS or MyInvois integrations.
- No card-brand/split-tender capture (data does not exist).
- No handling of Tangent day "locking" beyond recording the API response (we cannot detect a
  lock reliably from the documented responses).

## Env / files touched

- New: `config/services.php` (tangent block), `database/migrations/*_create_tangent_sales_hourly_table.php`,
  `app/Models/TangentSalesHourly.php`, `app/Services/Tangent/HourlySalesAggregator.php`,
  `app/Services/Tangent/TangentClient.php`,
  `app/Console/Commands/PushTangentSalesHourly.php`, `routes/console.php` (schedule line),
  tests under `tests/`.
- Docs: `.env.example`, `CLAUDE.md` (env vars + integration flow note).

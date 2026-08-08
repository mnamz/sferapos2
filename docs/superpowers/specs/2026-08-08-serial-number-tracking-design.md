# Serial-Number (S/N) Tracking — Design Spec

**Date:** 2026-08-08
**Status:** Approved, pending implementation

## Context

The client sells devices that must be tracked by individual serial number. Today,
`products.stock` is a single integer aggregate — there is no unit-level tracking.
Stock is deducted via `Product::decrement('stock', qty)` on order create
(`OrderController.php:534`) and restored/re-deducted on edit
(`OrderController.php:718-749`). `OrderItem` stores only a `quantity`.

The client wants: add stock as individual serials (key-in or scan), and at order
time let staff pick the specific serial(s) being sold. This must layer on top of the
existing aggregate-stock system without breaking POS listing, reports, or the
MyInvois e-invoice flow.

## Decisions (confirmed with client)

1. **Opt-in per product** via a new `serial_tracked` boolean. Untracked products
   behave exactly as today.
2. **Adding stock = keying/scanning serials** (bulk supported) for tracked products.
   Untracked products keep the integer restock/withdraw flow.
3. **At order time, quantity = number of serials picked**, and the quantity field is
   read-only for tracked products.
4. **On void/cancel/edit-removal, serials return to the `available` pool** so they can
   be resold — mirrors how aggregate stock is restored today.
5. **Serial numbers are globally unique** across the whole system.
6. **`products.stock` is kept in sync** = count of `available` serials for tracked
   products, so all downstream consumers (POS listing, reports, MyInvois) are unchanged.

## Data Model

### `serial_tracked` on products
- New boolean column, default `false`, added after `stock`.
- Added to `Product` `$fillable` and cast to boolean.
- New relationship `Product::serials(): HasMany`.

### `product_serials` table
| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| product_id | FK → products | cascade on delete |
| serial_number | string | globally unique among live rows |
| status | enum(`available`,`sold`) | default `available` |
| order_item_id | FK → order_items, nullable | precise link for display; destroyed on edit |
| order_id | FK → orders, nullable | durable link used to release on void/edit |
| timestamps, softDeletes | | |

Indexes: `unique(serial_number, deleted_at)` for global uniqueness while allowing
re-use of soft-deleted numbers; `index(product_id, status)` for fast available lookup.

Two statuses only (YAGNI). "Return to pool" is `sold → available` + clearing links,
not a separate status.

### New `ProductSerial` model
`SoftDeletes` + Auditing (parity with `Product`). Fillable:
`product_id, serial_number, status, order_item_id, order_id`. Relationships:
`product()`, `orderItem()`, `order()`. Scope `available()`.

### Stock sync
A single helper (`ProductSerialService::syncStock(product)`) sets
`product.stock = serials()->available()->count()` after every serial mutation.
Untracked products keep existing integer arithmetic untouched.

## Serial Lifecycle

| Event | Action | Stock effect |
|---|---|---|
| Add serials (tracked restock) | insert rows `available` | recount |
| Remove serial (only if `available`) | soft-delete row | recount |
| Order create allocates serial | `available → sold`, set `order_id`+`order_item_id` | recount |
| Order edit | release all serials for `order_id`, then re-allocate chosen | recount |
| Order void/cancel/delete | release all serials for `order_id` | recount |

## Backend Changes

### ProductController
- `getSerials(Product)` — list serials for Show page + order picker.
- `addSerials(Request, Product)` — bulk add; validate product is tracked; trim/dedupe;
  global-uniqueness validation; insert as `available`; recount; write audit like
  `adjustStock` (`ProductController.php:196-216`).
- `removeSerial(Request, Product, ProductSerial)` — only if `available`; soft-delete; recount.
- `adjustStock` (`:175-219`) — guard: reject integer restock/withdraw for tracked products.
- `store`/`update` validation (`:54-98`) — add `serial_tracked` boolean; when enabling,
  force `stock = 0`; disallow enabling while `stock > 0`.
- `getPosProducts` (`:125-141`) — add `serial_tracked` to the select list.

### OrderController
- `store` (`:512-535`) — for tracked items: derive quantity from serial count; fetch chosen
  serials with `lockForUpdate()` where `status=available`; if locked count ≠ requested → throw
  (rolls back at `:555`); create OrderItem; mark serials `sold` + set links; recount stock.
  Untracked path unchanged.
- `update` (`:718-749`) — release serials by `order_id` before item deletion, then re-allocate
  submitted serials in the recreation loop.
- `destroy` / `updateStatus→cancelled` (`:783-852`, `:772-781`) — release serials for the order
  before delete/on cancel; recount stock.

### Routes (`routes/pos.php`)
- `GET /products/{product}/serials` → `getSerials` (base auth group; cashiers pick at order time).
- `POST /products/{product}/serials` → `addSerials` (admin|manager).
- `DELETE /products/{product}/serials/{serial}` → `removeSerial` (admin|manager).

### ProductSerialService
`addSerials`, `removeSerial`, `allocate(orderItem, serials)`, `release(orderId)`,
`syncStock(product)` — shared by ProductController and OrderController (logic appears in
4 places, so extraction is justified, not speculative).

## Frontend Changes

- **Products/Create.vue + Edit.vue** — `serial_tracked` checkbox; when checked, hide/disable
  Stock input (Create) or make read-only (Edit); send `stock: 0` on create.
- **Products/Show.vue** (`:77-136`) — for tracked products, replace restock/withdraw with a
  Serial panel: bulk paste textarea + scan input (append on Enter) + "Add Serials"; list of
  available serials with per-row remove; show count = `product.stock`.
- **Orders/Create.vue** (`:283-364`, `:425-445`) — when a tracked product is added, show a serial
  picker (scan/select against `GET products.serials.index`); chosen serials as removable chips;
  `quantity = serials.length`, read-only; include `serials: []` per item in the save payload.
- **Orders/Edit.vue** — same picker; pre-load currently-allocated serials.

## Edge Cases
1. Duplicate serial on add — rejected by unique rule + in-request dedupe; error names the serial.
2. Race (two cashiers, same serial) — `lockForUpdate()` in allocation; mismatch rolls back.
3. Order edit changing serials — release-by-`order_id` before item deletion, then re-allocate.
4. Insufficient serials — count check throws, matching existing "Insufficient stock" behavior.
5. Mixed tracked + untracked in one order — per-item branch; no coupling.
6. MyInvois/reports — no impact; `order_items` shape unchanged, stock in sync. Serials not added
   to e-invoice payload (not a requirement).
7. Enabling `serial_tracked` on a product with stock — disallowed while `stock > 0` (validation).

## Testing (Pest, `tests/Feature/SerialTrackingTest.php`)
- Tracked product create forces `stock=0`; `adjustStock` rejected for tracked.
- Add serials: stock = available count; in-request dup rejected; global dup rejected; re-add of
  soft-deleted number allowed.
- Remove: only `available` removable; stock recounts.
- Order create: N serials → OrderItem `quantity=N`, serials `sold` with links, stock decremented;
  unavailable serial → 422 rollback (no partial writes).
- Race/insufficient → rollback.
- Void/delete: serials return to `available`, links nulled, stock restored.
- Edit: release old + allocate new; `order_id` link survives item recreation.
- Mixed order: untracked integer stock + tracked serials both correct.
- Regression: `getPosProducts` still filters `stock>0` and returns `serial_tracked`.

## Implementation Order
1. Migrations + `Product` model updates + `ProductSerial` model + factory.
2. `ProductSerialService` (add/remove/allocate/release/syncStock).
3. ProductController endpoints + `adjustStock` guard + validation + routes.
4. Product-side feature tests.
5. OrderController `store` allocation + derived quantity + locking.
6. OrderController `update`/`destroy`/`updateStatus` release + reallocate.
7. Order-side feature tests.
8. Frontend: product toggle, Show.vue panel, Orders Create/Edit picker.
9. Manual QA: POS listing, reports, MyInvois walk-in order.

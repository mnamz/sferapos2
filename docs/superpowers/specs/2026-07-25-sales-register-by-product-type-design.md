# Sales Register by Product Type (Group by Category) — Design

**Date:** 2026-07-25
**Status:** Approved (pending spec review)

## Purpose

Add a report that lists products sold within a date range, grouped by category, showing
quantity sold and gross sales per product, with per-category subtotals and a grand total.
This replicates a "Sales Register by Product Type" report from another POS system, adapted
to this app's data model.

## Data Model Notes / Adaptations

The reference report has filters (Outlet, Brand, Contact, Sales Person) that don't all map
cleanly to this app:

- **Outlet** — no multi-outlet/branch concept exists. Omitted.
- **Brand** — no Brand model. Derived from the **first word of `product_name`**
  (case-insensitive). This is a heuristic: most product names lead with the brand
  (`DJI`, `INSTA360`), but some lead with non-brands (`REPLACE`, `POSTAGE`,
  `WORKMANSHIP`, `GIMBAL`). The dropdown lists **all distinct first words, sorted
  alphabetically**, noise included — accepted by design.
- **Contact** — maps to `Customer` (`orders.customer_id`).
- **Sales Person** — maps to the order's user/cashier (`orders.user_id`).
- **Category** — maps to `products.category_id` → `categories.name`. This is the grouping key.

## Route & Code Layout

- **Route:** `GET /reports/sales-register` → `reports.sales-register`
- **Invoices bundle:** `GET /reports/sales-register/invoices` → `reports.sales-register.invoices`
- **Controller:** new methods on `ReportController`:
  - `salesRegister(Request $request)` — renders the report page
  - `salesRegisterInvoices(Request $request)` — streams a combined PDF of matching orders' invoices
  - `salesRegisterExport(Request $request)` — Excel export (PhpSpreadsheet)
- **Vue page:** `resources/js/pages/Reports/SalesRegister.vue`
- **Nav:** add "Sales Register" link in `AppSidebar.vue` under the existing Reports item,
  visible to the same roles that see Reports (hidden for staff).

## Query Logic

Base query joins `order_items` → `orders` → `products` → `categories`.

Constraints:
- `orders.status != 'cancelled'`
- soft-deleted orders excluded (Eloquent default; ensure the join respects `orders.deleted_at IS NULL`)
- date range on `orders.created_at` between `start_date 00:00:00` and `end_date 23:59:59`

Grouping / aggregation:
- Group by category, then by product. Product identity uses `order_items.product_name`
  (the sale-time snapshot) so deleted products still appear correctly.
- Per product:
  - `Quantity = SUM(order_items.quantity)`
  - `Sales = SUM(order_items.total)` — gross line total (price × qty), before order-level
    discount/tax. Delivery excluded (not an order item).
- Per category: subtotal of quantity and sales.
- Grand total: sum across all categories.

Category resolution: `order_items` links to `products.category_id`. When a product is
soft-deleted or `product_id` is null, fall back to an "Uncategorized" bucket so no sales
are silently dropped.

## Filters (all optional, query params)

| Filter | Param | Source |
|--------|-------|--------|
| Date range | `start_date`, `end_date` | `orders.created_at`; defaults to current month |
| Brand | `brand` | first word of `order_items.product_name` (case-insensitive) |
| Category | `category_id` | `products.category_id` |
| Sales Person | `user_id` | `orders.user_id` |
| Customer | `customer_id` | `orders.customer_id` |
| Payment method | `payment_method` | `orders.payment_method` |
| Delivery method | `delivery_method` | `orders.delivery_method` |

Filter option lists passed to the view:
- Brands: distinct first words of all product names, sorted.
- Categories: all categories.
- Sales persons: all users (id + name).
- Customers: customer list (id + name).
- Methods: existing known values (match the Sales Report page's method options).

## Inertia Props

```
Inertia::render('Reports/SalesRegister', [
    'groups' => [
        [ 'category' => 'DIGITAL CAMERA',
          'products' => [ ['name' => ..., 'quantity' => ..., 'sales' => ...], ... ],
          'quantity_total' => ...,
          'sales_total' => ... ],
        ...
    ],
    'grandTotal' => [ 'quantity' => ..., 'sales' => ... ],
    'filterOptions' => [ 'brands' => [...], 'categories' => [...], 'salespersons' => [...], 'customers' => [...], 'paymentMethods' => [...], 'deliveryMethods' => [...] ],
    'filters' => [ 'start_date', 'end_date', 'brand', 'category_id', 'user_id', 'customer_id', 'payment_method', 'delivery_method' ],
])
```

## View Layout (`SalesRegister.vue`)

1. **Filter bar:** date range, brand dropdown, category dropdown, salesperson dropdown,
   customer dropdown, payment/delivery method dropdown, Apply button. "Export Excel" and
   "Download Invoices" buttons (links carrying the current query string).
2. **Header block** echoing active filters, mirroring the reference: Duration, Outlet (All),
   Brand, Category, Contact, Sales Person, Date Printed (now).
3. **Grouped tables:** one section per category — a category header row, then rows of
   `Product | Quantity | Sales`, then a category **Total** row.
4. **Grand Total** row at the bottom.

Amounts formatted to 2 decimals; quantities as integers.

## Export (Excel)

`salesRegisterExport` builds a PhpSpreadsheet workbook mirroring the grouped layout:
category header rows, product rows (name, quantity, sales), category subtotal rows, and a
grand total row. Bold headers/totals; number formatting with comma separators; streamed as
`sales-register-{start}-to-{end}.xlsx`. Follows the pattern in `ReportController@export`.

## Download Invoices (combined PDF)

`salesRegisterInvoices` loads all orders matching the **same filters** (date/brand/category/
salesperson/customer/method), then renders each order's invoice using the existing
`pdf.invoice` Blade view and concatenates them into a single DOMPDF document streamed to the
browser. Reuses the existing invoice rendering logic (extract a shared helper if needed to
avoid duplicating `InvoiceController::generate`). If no orders match, return a friendly
message/redirect back rather than an empty PDF.

Note: a brand/category filter constrains by product; an order that contains at least one
matching line item is included (the whole invoice, not just matching lines).

## Testing

Feature tests (Pest) in `tests/Feature/`:
- Grouping & totals: seed orders/items across two categories; assert `groups`, per-category
  totals, and grand total.
- Cancelled/soft-deleted orders excluded from figures.
- Date-range filter boundaries (inclusive start/end).
- Brand filter matches on first word, case-insensitive.
- Category / salesperson / customer / method filters each narrow results.
- Sales amount uses gross line total (`SUM(order_items.total)`), delivery excluded.
- Export route returns 200 with an xlsx content type.
- Invoices route returns a PDF for matching orders and handles the empty-match case.

## Out of Scope

- Multi-outlet/branch support.
- A real Brand model or per-product brand field.
- Net-of-discount per-item sales (order-level discount is not stored per line item).

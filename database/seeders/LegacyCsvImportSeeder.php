<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LegacyCsvImportSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $basePath = storage_path('dsr');

            $categoryPath = $basePath . DIRECTORY_SEPARATOR . 'tbl_category.csv';
            $clientPath = $basePath . DIRECTORY_SEPARATOR . 'tbl_client.csv';
            $productPath = $basePath . DIRECTORY_SEPARATOR . 'tbl_product.csv';
            $invoicePath = $basePath . DIRECTORY_SEPARATOR . 'tbl_invoice.csv';
            $invoiceDetailsPath = $basePath . DIRECTORY_SEPARATOR . 'tbl_invoice_details.csv';

            // 1) Categories
            $categories = $this->readCsvAssoc($categoryPath);
            $categoryNameToId = [];
            foreach ($categories as $row) {
                $name = $this->trimOrNull($row['category'] ?? null);
                if (!$name) {
                    continue;
                }

                // Create if not exists by name
                $category = Category::firstOrCreate(
                    ['name' => $name],
                    [
                        'description' => null,
                        'status' => true,
                    ]
                );

                $categoryNameToId[$name] = $category->id;
            }

            // 2) Customers (tbl_client -> customers)
            $clients = $this->readCsvAssoc($clientPath);
            $customerRows = [];
            foreach ($clients as $row) {
                $legacyId = $this->intOrNull($row['id'] ?? null);
                if ($legacyId === null) {
                    continue;
                }
                // Skip legacy id 0 (WALK-IN) to avoid creating PK=0; orders will use NULL for this case
                if ($legacyId === 0) {
                    continue;
                }

                $customerRows[] = [
                    'id' => $legacyId, // preserve legacy id so orders can reference it
                    'name' => $this->trimOrNull($row['name'] ?? null),
                    'email' => $this->nullIfEmpty($row['email'] ?? null),
                    'phone' => $this->trimOrNull($row['phone'] ?? null),
                    // Prefer shipping address if present; else use address
                    'address' => $this->buildAddress($row),
                    'status' => true,
                    'created_at' => $this->datetimeOrNow($row['timestamp'] ?? null),
                    'updated_at' => $this->datetimeOrNow($row['timestamp'] ?? null),
                ];
            }
            if (!empty($customerRows)) {
                DB::table('customers')->upsert($customerRows, ['id'], ['name','email','phone','address','status','created_at','updated_at']);
            }

            // Build a fast lookup of existing customer ids after upsert
            $existingCustomerIds = array_flip(DB::table('customers')->pluck('id')->all());

            // 3) Products (preserve legacy id; map category by name)
            $products = $this->readCsvAssoc($productPath);
            // Ensure an 'Uncategorized' fallback exists for products without a category
            $uncategorizedId = Category::firstOrCreate(
                ['name' => 'Uncategorized'],
                ['description' => null, 'status' => true]
            )->id;
            $productRows = [];
            foreach ($products as $row) {
                $legacyId = $this->intOrNull($row['pid'] ?? null);
                if ($legacyId === null) {
                    continue;
                }

                $categoryName = $this->trimOrNull($row['pcategory'] ?? null);
                // If the product references a category name not present in the category CSV, create it on the fly
                if ($categoryName) {
                    if (!array_key_exists($categoryName, $categoryNameToId)) {
                        $newCategory = Category::firstOrCreate(
                            ['name' => $categoryName],
                            ['description' => null, 'status' => true]
                        );
                        $categoryNameToId[$categoryName] = $newCategory->id;
                    }
                }
                $categoryId = $categoryName ? ($categoryNameToId[$categoryName] ?? $uncategorizedId) : $uncategorizedId;

                $name = $this->trimOrNull($row['pname'] ?? null);
                $description = $this->nullIfEmpty($row['pdescription'] ?? null);
                $purchasePrice = $this->decimalOrNull($row['purchaseprice'] ?? null);
                $salePrice = $this->decimalOrNull($row['saleprice'] ?? null);
                $stock = $this->intOrNull($row['pstock'] ?? null) ?? 0;
                $barcode = $this->trimOrNull($row['barcode'] ?? null);

                $productRows[] = [
                    'id' => $legacyId,
                    'name' => $name,
                    'description' => $description,
                    'price' => $salePrice ?? 0,
                    'cost_price' => $purchasePrice ?? 0,
                    'stock' => $stock,
                    'category_id' => $categoryId,
                    'image' => null,
                    'status' => true,
                    'barcode' => $barcode,
                    'supplier_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (!empty($productRows)) {
                DB::table('products')->upsert($productRows, ['id'], [
                    'name','description','price','cost_price','stock','category_id','image','status','barcode','supplier_id','created_at','updated_at'
                ]);
            }

            // Build a fast lookup of existing product ids and names after upsert
            $existingProductIds = array_flip(DB::table('products')->pluck('id')->all());
            $productIdToName = DB::table('products')->pluck('name', 'id')->all();

            // Prepare payment method mapping
            $paymentMap = [
                'Cash' => 'cash',
                'Card' => 'card',
                'Online Transfer' => 'bank_transfer',
                'Bank Transfer' => 'bank_transfer',
                'E-Wallet' => 'card', // best-effort mapping
            ];

            $firstUserId = DB::table('users')->min('id') ?? 1;

            // 4) Orders (tbl_invoice -> orders; preserve invoice_id as id)
            $invoices = $this->readCsvAssoc($invoicePath);
            $orderRows = [];
            foreach ($invoices as $row) {
                $legacyId = $this->intOrNull($row['invoice_id'] ?? null);
                if ($legacyId === null) {
                    continue;
                }

                $customerId = $this->intOrNull($row['customer_name'] ?? null); // actually client id per user
                if ($customerId === 0 || ($customerId !== null && !isset($existingCustomerIds[$customerId]))) {
                    $customerId = null;
                }
                $orderDate = $this->dateOrNull($row['order_date'] ?? null);
                $timestamp = $this->datetimeOrNull($row['timestamp'] ?? null);

                $subtotal = $this->decimalOrNull($row['subtotal'] ?? null) ?? 0;
                $tax = $this->decimalOrNull($row['tax'] ?? null) ?? 0;
                $total = $this->decimalOrNull($row['total'] ?? null) ?? 0;
                $paid = $this->decimalOrNull($row['paid'] ?? null) ?? 0;
                $profit = $this->decimalOrNull($row['profit'] ?? null) ?? 0;
                $status = 'completed';

                $createdAt = $orderDate ? ($orderDate . ' 00:00:00') : ($timestamp ?? now());
                $updatedAt = $timestamp ?? $createdAt;
                $changeAmount = $paid > $total ? ($paid - $total) : 0;

                $orderRows[] = [
                    'id' => $legacyId,
                    'customer_id' => $customerId,
                    'user_id' => $firstUserId,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'profit' => $profit,
                    'paid_amount' => $paid,
                    'change_amount' => $changeAmount,
                    'status' => $status,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ];
            }
            if (!empty($orderRows)) {
                DB::table('orders')->upsert($orderRows, ['id'], [
                    'customer_id','user_id','subtotal','tax','total','profit','paid_amount','change_amount','status','created_at','updated_at'
                ]);
            }

            // Build a lookup of existing order ids after upsert
            $existingOrderIds = array_flip(DB::table('orders')->pluck('id')->all());

            // 5) Order Items (tbl_invoice_details -> order_items)
            $invoiceDetails = $this->readCsvAssoc($invoiceDetailsPath);
            $orderItemRows = [];
            foreach ($invoiceDetails as $row) {
                $legacyId = $this->intOrNull($row['id'] ?? null);
                if ($legacyId === null) {
                    continue;
                }

                $orderId = $this->intOrNull($row['invoice_id'] ?? null);
                // Skip if order does not exist (e.g., legacy invoice_id 0 or missing)
                if ($orderId === null || !isset($existingOrderIds[$orderId])) {
                    continue;
                }
                $productId = $this->intOrNull($row['product_id'] ?? null);
                if ($productId !== null && !isset($existingProductIds[$productId])) {
                    $productId = null;
                }
                $productName = $this->trimOrNull($row['product_name'] ?? null);
                $qty = $this->intOrNull($row['qty'] ?? null) ?? 0;
                $price = $this->decimalOrNull($row['price'] ?? null) ?? 0;
                $discount = $this->decimalOrNull($row['dis'] ?? null) ?? 0;
                $profit = $this->decimalOrNull($row['profit'] ?? null) ?? 0;
                $remark = $this->trimOrNull($row['remark'] ?? null);
                $orderDate = $this->dateOrNull($row['order_date'] ?? null);
                $createdAt = $orderDate ? ($orderDate . ' 00:00:00') : now();

                $total = max(0, ($qty * $price) - $discount);

                // Ensure product_name is populated; fallback to products table by product_id
                if ($productName === null && $productId !== null && isset($productIdToName[$productId])) {
                    $productName = $this->trimOrNull($productIdToName[$productId]);
                }
                if ($productName === null) {
                    $productName = 'Unknown Product';
                }

                $orderItemRows[] = [
                    'id' => $legacyId,
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'product_name' => $productName,
                    'quantity' => $qty,
                    'price' => $price,
                    'cost_price' => 0,
                    'total' => $total,
                    'profit' => $profit,
                    'remark' => $remark,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
            if (!empty($orderItemRows)) {
                DB::table('order_items')->upsert($orderItemRows, ['id'], [
                    'order_id','product_id','product_name','quantity','price','cost_price','total','profit','remark','created_at','updated_at'
                ]);
            }
        });
    }

    private function readCsvAssoc(string $path): array
    {
        if (!is_readable($path)) {
            Log::warning('CSV not readable: ' . $path);
            return [];
        }

        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                fclose($handle);
                return [];
            }
            // Normalize headers to lowercase
            $headers = array_map(function ($h) {
                return strtolower(trim((string) $h, "\xEF\xBB\xBF\x00\t\n\r\0\x0B\""));
            }, $headers);

            while (($data = fgetcsv($handle)) !== false) {
                // Skip entirely empty rows
                if (count(array_filter($data, fn($v) => $v !== null && $v !== '')) === 0) {
                    continue;
                }
                $row = [];
                foreach ($headers as $i => $header) {
                    $row[$header] = $data[$i] ?? null;
                }
                $rows[] = $row;
            }
            fclose($handle);
        }
        return $rows;
    }

    private function trimOrNull($value): ?string
    {
        if ($value === null) return null;
        $v = trim((string) $value);
        return $v === '' || strtoupper($v) === 'NULL' ? null : $v;
    }

    private function nullIfEmpty($value): ?string
    {
        return $this->trimOrNull($value);
    }

    private function intOrNull($value): ?int
    {
        $v = $this->trimOrNull($value);
        if ($v === null) return null;
        return is_numeric($v) ? (int) $v : null;
    }

    private function decimalOrNull($value): ?float
    {
        $v = $this->trimOrNull($value);
        if ($v === null) return null;
        // Handle cases like "0.00"
        $v = str_replace([','], ['',], $v);
        return is_numeric($v) ? (float) $v : null;
    }

    private function dateOrNull($value): ?string
    {
        $v = $this->trimOrNull($value);
        if ($v === null) return null;
        // Expecting YYYY-MM-DD; return as-is
        return $v;
    }

    private function datetimeOrNull($value): ?string
    {
        $v = $this->trimOrNull($value);
        return $v ?: null;
    }

    private function datetimeOrNow($value): string
    {
        return $this->datetimeOrNull($value) ?? now()->toDateTimeString();
    }

    private function buildAddress(array $row): ?string
    {
        // Combine best-effort address parts from client CSV
        $parts = [];
        foreach (['saddress','scity','spostcode','sstate'] as $key) {
            $val = $this->trimOrNull($row[$key] ?? null);
            if ($val) $parts[] = $val;
        }
        if (empty($parts)) {
            foreach (['address','city','postcode','state'] as $key) {
                $val = $this->trimOrNull($row[$key] ?? null);
                if ($val) $parts[] = $val;
            }
        }
        if (empty($parts)) return null;
        return implode(', ', $parts);
    }
}



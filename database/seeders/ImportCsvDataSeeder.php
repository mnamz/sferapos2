<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportCsvDataSeeder extends Seeder
{
    protected $siteName;

    public function __construct()
    {
        $this->siteName = env('SITE_URL', 'default');
    }

    public function run(): void
    {
        // Import Shop Settings
        $shopSettings = [
            'shop_name' => 'Default Shop',
            'shop_address' => 'Default Address',
            'shop_phone' => 'Default Phone',
            'shop_email' => 'default@email.com',
            'currency' => 'RM',
            'tax_percentage' => 6.00,
            'logo_path' => null,
            'invoice_logo_path' => null,
            'company_number' => null,
            'tax_number' => null,
            'payment_details' => null,
            'footer_text' => 'Thank you for your business!',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Override settings for Sunway
        if (str_contains(strtolower($this->siteName), 'sunway')) {
            $shopSettings = [
                'shop_name' => 'MY RC CORNER TRADING',
                'shop_address' => 'E-03-07, Sunway Geo Avenue, Jalan Lagoon Selatan, Sunway South Quay, 47500 Bandar Sunway, Selangor',
                'shop_phone' => '601188889996',
                'shop_email' => 'admin@myrccornertrading.com',
                'currency' => 'RM',
                'tax_percentage' => 0.00,
                'logo_path' => null,
                'invoice_logo_path' => null,
                'company_number' => '202303144699 (003499115-D)',
                'footer_text' => 'PUBLIC BANK 3235196200
MY RC CORNER TRADING',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (str_contains(strtolower($this->siteName), 'kl')) {
            $shopSettings = [
                'shop_name' => 'MY RC CORNER TRADING',
                'shop_address' => 'A-01-05, First Floor, Tower A, M Vertica Retail Shop,
555, Jalan Cheras, Taman Pertama,
56000 Kuala Lumpur',
                'shop_phone' => '601188889996',
                'shop_email' => 'admin@myrccornertrading.com',
                'currency' => 'RM',
                'tax_percentage' => 0.00,
                'logo_path' => null,
                'invoice_logo_path' => null,
                'company_number' => '202303144699 (003499115-D)',
                'footer_text' => 'PUBLIC BANK 3235196200
MY RC CORNER TRADING',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (str_contains(strtolower($this->siteName), 'johor')) {
            $shopSettings = [
                'shop_name' => 'MY RC CORNER TRADING',
                'shop_address' => 'C-01-10, Pangsapuri Duta Hijauan,
Jalan Indah 15/2, Taman Bukit Indah,
79100 Iskandar Puteri,
Johor',
                'shop_phone' => '601188889996',
                'shop_email' => 'admin@myrccornertrading.com',
                'currency' => 'RM',
                'tax_percentage' => 0.00,
                'logo_path' => null,
                'invoice_logo_path' => null,
                'company_number' => '202303144699 (003499115-D)',
                'footer_text' => 'PUBLIC BANK 3235196200
MY RC CORNER TRADING',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (str_contains(strtolower($this->siteName), 'setapak')) {
            $shopSettings = [
                'shop_name' => 'METAJO MARKETING',
                'shop_address' => 'Platinum Walk, No.65-1, Block E
No.2, Jalan Langkawi Setapak
53300 Wilayah Persekutuan',
                'shop_phone' => '601115555520',
                'shop_email' => 'admin@dronecaremy.com',
                'currency' => 'RM',
                'tax_percentage' => 0.00,
                'logo_path' => null,
                'invoice_logo_path' => null,
                'company_number' => '201503249038 (SA0351823-T)',
                'footer_text' => 'CIMB BANK 8604617450
METAJO MARKETING',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (str_contains(strtolower($this->siteName), 'puchong')) {
            $shopSettings = [
                'shop_name' => 'METAJO MARKETING',
                'shop_address' => '57A, JALAN PU 7/4
PUSAT BANDAR PUCHONG
47100 PUCHONG
SELANGOR',
                'shop_phone' => '601115555520',
                'shop_email' => 'admin@dronecaremy.com',
                'currency' => 'RM',
                'tax_percentage' => 0.00,
                'logo_path' => null,
                'invoice_logo_path' => null,
                'company_number' => '201503249038 (SA0351823-T)',
                'footer_text' => 'CIMB BANK 8604617450
METAJO MARKETING',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (str_contains(strtolower($this->siteName), 'repair.dronecare.my')) {
            $shopSettings = [
                'shop_name' => 'METAJO MARKETING',
                'shop_address' => '57A, JALAN PU 7/4
PUSAT BANDAR PUCHONG
47100 PUCHONG
SELANGOR',
                'shop_phone' => '601115555520',
                'shop_email' => 'admin@dronecaremy.com',
                'currency' => 'RM',
                'tax_percentage' => 0.00,
                'logo_path' => null,
                'invoice_logo_path' => null,
                'company_number' => '201503249038 (SA0351823-T)',
                'footer_text' => 'CIMB BANK 8604617450
METAJO MARKETING',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        

        // Insert or update shop settings
        DB::table('shop_settings')->updateOrInsert(
            ['id' => 1], // Assuming we want to use ID 1 for the main shop settings
            $shopSettings
        );

        // Import Categories
        $categories = $this->readCsvFile(storage_path($this->siteName.'/tbl_category.csv'));
        $categoryMap = [];
        
        foreach ($categories as $category) {
            $id = DB::table('categories')->insertGetId([
                'name' => $category['category'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $categoryMap[$category['category']] = $id;
        }

        // Import Products
        $products = $this->readCsvFile(storage_path($this->siteName.'/tbl_product.csv'));
        $productMap = [];
        
        foreach ($products as $product) {
            // Find category by name
            $category = DB::table('categories')
                ->where('name', $product['pcategory'])
                ->first();

            $id = DB::table('products')->insertGetId([
                'name' => $product['pname'],
                'description' => $product['pdescription'],
                'price' => $product['saleprice'],
                'cost_price' => $product['purchaseprice'],
                'category_id' => $category ? $category->id : 1,
                'stock' => $product['pstock'],
                'status' => $product['status'] ?: 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $productMap[$product['pid']] = $id;
        }

        // Import Customers
        $customers = $this->readCsvFile(storage_path($this->siteName.'/tbl_client.csv'));
        $customerMap = [];
        
        foreach ($customers as $customer) {
            $address = trim($customer['address'] . ' ' . $customer['address2']);
            
            $id = DB::table('customers')->insertGetId([
                'name' => $customer['name'],
                'address' => $address,
                'phone' => $customer['phone'],
                'email' => $customer['email'] ?: null,
                'created_at' => $customer['timestamp'],
                'updated_at' => now(),
            ]);
            $customerMap[$customer['id']] = $id;
        }

        // Import Orders and Order Items
        $invoices = $this->readCsvFile(storage_path($this->siteName.'/tbl_invoice.csv'));
        $invoiceDetails = $this->readCsvFile(storage_path($this->siteName.'/tbl_invoice_details.csv'));
        
        foreach ($invoices as $invoice) {
            $orderId = DB::table('orders')->insertGetId([
                'customer_id' => $customerMap[$invoice['customer_name']] ?? null,
                'user_id' => 1, // Default to first user
                'subtotal' => $this->handleDecimalValue($invoice['subtotal']),
                'tax' => $this->handleDecimalValue($invoice['tax']),
                'delivery_cost' => 0,
                'discount' => $this->handleDecimalValue($invoice['discount'] ?? 0),
                'total' => $this->handleDecimalValue($invoice['total']),
                'profit' => $this->handleDecimalValue($invoice['profit'] ?? 0),
                'paid_amount' => $this->handleDecimalValue($invoice['paid']),
                'due_amount' => $this->handleDecimalValue($invoice['due']),
                'change_amount' => 0,
                'payment_method' => strtolower($invoice['payment_type']),
                'delivery_method' => 'pickup',
                'remarks' => $invoice['remark'] ?? '',
                'status' => 'completed',
                'created_at' => $this->handleDateTimeValue($invoice['order_date']),
                'updated_at' => $this->handleDateTimeValue($invoice['order_date']),
            ]);

            // Import Order Items
            $orderItems = array_filter($invoiceDetails, function($item) use ($invoice) {
                return $item['invoice_id'] == $invoice['invoice_id'];
            });

            foreach ($orderItems as $item) {
                $productId = $productMap[$item['product_id']] ?? null;
                $product = null;
                
                if ($productId) {
                    $product = DB::table('products')->where('id', $productId)->first();
                }

                $quantity = $item['qty'];
                $price = $item['price'];
                $costPrice = $product ? $product->cost_price : 0;
                $total = $price * $quantity;
                $profit = ($price - $costPrice) * $quantity;

                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'product_name' => $product ? $product->name : $item['product_name'],
                    'quantity' => $quantity,
                    'price' => $price,
                    'cost_price' => $costPrice,
                    'total' => $total,
                    'profit' => $profit,
                    'remark' => $item['remark'] ?? null,
                    'created_at' => $this->handleDateTimeValue($item['order_date']),
                    'updated_at' => $this->handleDateTimeValue($item['order_date']),
                ]);
            }
        }
    }

    private function readCsvFile($path)
    {
        $content = File::get($path);
        $lines = explode("\n", $content);
        $headers = str_getcsv(array_shift($lines));
        $headerCount = count($headers);
        
        $data = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $values = str_getcsv($line);
            
            // Ensure we have the same number of values as headers
            if (count($values) !== $headerCount) {
                // Pad with empty strings if we have fewer values
                $values = array_pad($values, $headerCount, '');
                // Or trim if we have more values
                $values = array_slice($values, 0, $headerCount);
            }
            
            $row = array_combine($headers, $values);
            $data[] = $row;
        }
        
        return $data;
    }

    private function handleDecimalValue($value)
    {
        // Remove any non-numeric characters except decimal point and negative sign
        $value = preg_replace('/[^0-9.\-]/', '', $value);
        
        // Handle multiple decimal points by keeping only the first one
        $parts = explode('.', $value);
        if (count($parts) > 2) {
            $value = $parts[0] . '.' . implode('', array_slice($parts, 1));
        }
        
        // Convert to float and format to 2 decimal places
        return number_format((float)$value, 2, '.', '');
    }

    private function handleDateTimeValue($value)
    {
        // If empty or null, return current timestamp
        if (empty($value)) {
            return now();
        }

        // Try to parse the date using different formats
        $formats = [
            'Y-m-d H:i:s',    // 2024-03-15 14:30:00
            'Y-m-d H:i',      // 2024-03-15 14:30
            'Y-m-d',          // 2024-03-15
            'd/m/Y H:i:s',    // 15/03/2024 14:30:00
            'd/m/Y H:i',      // 15/03/2024 14:30
            'd/m/Y',          // 15/03/2024
            'm/d/Y H:i:s',    // 03/15/2024 14:30:00
            'm/d/Y H:i',      // 03/15/2024 14:30
            'm/d/Y',          // 03/15/2024
        ];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        // If all parsing attempts fail, return current timestamp
        return now();
    }
} 
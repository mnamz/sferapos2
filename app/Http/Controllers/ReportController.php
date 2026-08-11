<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShopSettings;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        // Profit/cost figures are visible to admins only; managers/staff see sales only.
        $canViewProfit = (bool) $request->user()?->hasRole('admin');

        $query = Order::query()
            ->whereBetween('orders.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->where('orders.status', '!=', 'cancelled')
            ->when($request->input('delivery_method'), function ($query, $method) {
                if ($method === 'in-store') {
                    $query->whereIn('delivery_method', ['walk-in', 'delivery', 'pickup']);
                } else {
                    $query->where('delivery_method', $method);
                }
            });

        // Calculate summary statistics
        $summary = [
            'total_sales' => $query->clone()->sum('orders.total'),
            'total_orders' => $query->clone()->count(),
            'average_order_value' => $query->clone()->avg('orders.total'),
            'total_tax' => $query->clone()->sum('orders.tax'),
        ];

        if ($canViewProfit) {
            $summary['total_profit'] = $query->clone()->sum('orders.profit');
        }

        // Get daily sales data for the chart
        $dailySales = $query->clone()
            ->select(
                DB::raw('DATE(orders.created_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(orders.total) as total_sales'),
                DB::raw('SUM(orders.tax) as total_tax')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Get orders for the table
        $orders = $query->clone()
            ->with(['customer', 'user'])
            ->when($request->input('sort_column'), function ($query, $column) use ($request) {
                $direction = $request->input('sort_direction', 'asc');

                // Map frontend column names to database column names
                $columnMap = [
                    'id' => 'orders.id',
                    'customer_name' => 'customers.name',
                    'subtotal' => 'orders.subtotal',
                    'total' => 'orders.total',
                    'tax' => 'orders.tax',
                    'profit' => 'orders.profit',
                    'due' => 'orders.due_amount',
                    'status' => 'orders.status',
                    'payment_status' => 'orders.paid_amount',
                    'cashier_name' => 'user_id',
                    'created_at' => 'orders.created_at',
                ];

                $dbColumn = $columnMap[$column] ?? $column;

                if ($dbColumn === 'customers.name') {
                    $query->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
                        ->select('orders.*')  // Select all order fields
                        ->orderBy('customers.name', $direction)
                        ->orderBy('orders.id', $direction); // Secondary sort by order ID
                } elseif ($dbColumn === 'user_id') {
                    $query->join('users', 'orders.user_id', '=', 'users.id')
                        ->select('orders.*')  // Select all order fields
                        ->orderBy('users.name', $direction);
                } else {
                    $query->orderBy($dbColumn, $direction);
                }
            }, function ($query) {
                $query->latest('orders.created_at');
            })
            ->paginate(10)
            ->withQueryString()
            ->through(function ($order) use ($canViewProfit) {
                $data = [
                    'id' => $order->id,
                    'customer_name' => $order->customer ? $order->customer->name : 'Walk-in Customer',
                    'subtotal' => number_format($order->subtotal, 2),
                    'total' => number_format($order->total, 2),
                    'tax' => number_format($order->tax, 2),
                    'due' => number_format($order->due_amount, 2),
                    'status' => $order->status,
                    'payment_status' => $order->paid_amount >= $order->total ? 'paid' :
                        ($order->paid_amount > 0 ? 'partial' : 'pending'),
                    'created_at' => Carbon::parse($order->created_at)->format('Y-m-d H:i:s'),
                    'delivery_method' => $order->delivery_method,
                    'payment_method' => $order->payment_method,
                    'cashier_name' => $order->user->name,
                ];

                if ($canViewProfit) {
                    $data['profit'] = number_format($order->profit, 2);
                }

                return $data;
            });

        // Get profit details (cost breakdown) — admin only.
        $profitDetails = $canViewProfit
            ? DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereBetween('orders.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->where('orders.status', '!=', 'cancelled')
                ->select(
                    'order_items.product_id',
                    'order_items.product_name',
                    DB::raw('SUM(order_items.quantity) as quantity_sold'),
                    DB::raw('AVG(order_items.cost_price) as cost_price'),
                    DB::raw('AVG(order_items.price) as selling_price'),
                    DB::raw('SUM(order_items.total) as total_revenue'),
                    DB::raw('SUM(order_items.cost_price * order_items.quantity) as total_cost'),
                    DB::raw('SUM(order_items.profit) as profit')
                )
                ->groupBy('order_items.product_id', 'order_items.product_name')
                ->orderByDesc('profit')
                ->get()
            : [];

        return Inertia::render('Reports/Index', [
            'summary' => $summary,
            'dailySales' => $dailySales,
            'orders' => $orders,
            'profitDetails' => $profitDetails,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'delivery_method' => $request->input('delivery_method'),
                'sort_column' => $request->input('sort_column'),
                'sort_direction' => $request->input('sort_direction'),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        $orders = Order::query()
            ->with(['customer', 'user'])
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->where('status', '!=', 'cancelled')
            ->when($request->input('delivery_method'), function ($query, $method) {
                if ($method === 'in-store') {
                    $query->whereIn('delivery_method', ['walk-in', 'delivery']);
                } else {
                    $query->where('delivery_method', $method);
                }
            })
            ->latest()
            ->get();

        // Profit is an admin-only column; managers/staff get the sales export without it.
        $canViewProfit = (bool) $request->user()?->hasRole('admin');

        // Ordered column definitions. The profit column is inserted only for admins,
        // so every other column stays contiguous regardless of role.
        $columns = [
            ['header' => 'Order #', 'value' => fn ($o) => $o->id],
            ['header' => 'Customer', 'value' => fn ($o) => $o->customer ? $o->customer->name : 'Walk-in Customer'],
            ['header' => 'Total', 'value' => fn ($o) => $o->total, 'number' => true],
            ['header' => 'Tax', 'value' => fn ($o) => $o->tax, 'number' => true],
        ];

        if ($canViewProfit) {
            $columns[] = ['header' => 'Profit', 'value' => fn ($o) => $o->profit, 'number' => true];
        }

        $columns = array_merge($columns, [
            ['header' => 'Due', 'value' => fn ($o) => $o->due_amount, 'number' => true],
            ['header' => 'Payment', 'value' => fn ($o) => $o->payment_method],
            ['header' => 'Status', 'value' => fn ($o) => ucfirst($o->status)],
            ['header' => 'Payment Status', 'value' => fn ($o) => $o->paid_amount >= $o->total ? 'Paid' :
                ($o->paid_amount > 0 ? 'Partial' : 'Pending')],
            ['header' => 'Cashier', 'value' => fn ($o) => $o->user->name],
            ['header' => 'Date', 'value' => fn ($o) => $o->created_at->format('Y-m-d H:i:s')],
            ['header' => 'Items', 'value' => fn ($o) => json_encode($o->items->pluck('product_name')->toArray())],
            ['header' => 'Item Remarks', 'value' => fn ($o) => json_encode($o->items->pluck('remark')->filter()->values()->all())],
            ['header' => 'Delivery Method', 'value' => fn ($o) => $o->delivery_method],
        ]);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // Header row
        foreach ($columns as $index => $column) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($letter.'1', $column['header']);
            $sheet->getStyle($letter.'1')->getFont()->setBold(true);
        }

        // Data rows
        $row = 2;
        foreach ($orders as $order) {
            foreach ($columns as $index => $column) {
                $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValue($letter.$row, ($column['value'])($order));

                if (! empty($column['number'])) {
                    $sheet->getStyle($letter.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                }
            }
            $row++;
        }

        // Auto-size columns
        $lastLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns));
        foreach (range('A', $lastLetter) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'orders_report_'.$startDate.'_to_'.$endDate.'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

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
            'paymentMethods' => ['cash', 'card', 'e-wallet', 'online_transfer', 'bank_transfer'],
            'deliveryMethods' => ['walk-in', 'delivery', 'pickup', 'shopee', 'lazada', 'tiktok'],
        ];
    }

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
            ->whereBetween('orders.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->when($request->input('brand'), function ($query, $brand) {
                $query->where(function ($q) use ($brand) {
                    $q->where('order_items.product_name', 'like', $brand.' %')
                        ->orWhere('order_items.product_name', $brand);
                });
            })
            ->when($request->input('category_id'), fn ($query, $id) => $query->where('products.category_id', $id))
            ->when($request->input('user_id'), fn ($query, $id) => $query->where('orders.user_id', $id))
            ->when($request->input('customer_id'), fn ($query, $id) => $query->where('orders.customer_id', $id))
            ->when($request->input('payment_method'), fn ($query, $m) => $query->where('orders.payment_method', $m))
            ->when($request->input('delivery_method'), fn ($query, $m) => $query->where('orders.delivery_method', $m));
    }

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

        $spreadsheet = new Spreadsheet;
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
            $sheet->setCellValue('A'.$row, 'Total');
            $sheet->setCellValue('B'.$row, $catQty);
            $sheet->setCellValue('C'.$row, $catSales);
            $sheet->getStyle('A'.$row.':C'.$row)->getFont()->setBold(true);
            $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
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
                $sheet->setCellValue('A'.$row, $r->category);
                $sheet->getStyle('A'.$row)->getFont()->setBold(true);
                $row++;
            }

            $qty = (int) $r->quantity;
            $sales = (float) $r->sales;

            $sheet->setCellValue('A'.$row, $r->product_name);
            $sheet->setCellValue('B'.$row, $qty);
            $sheet->setCellValue('C'.$row, $sales);
            $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $row++;

            $catQty += $qty;
            $catSales += $sales;
            $grandQty += $qty;
            $grandSales += $sales;
        }

        if ($currentCategory !== null) {
            $writeCategoryTotal();
        }

        $sheet->setCellValue('A'.$row, 'Grand Total');
        $sheet->setCellValue('B'.$row, $grandQty);
        $sheet->setCellValue('C'.$row, $grandSales);
        $sheet->getStyle('A'.$row.':C'.$row)->getFont()->setBold(true);
        $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

        foreach (range('A', 'C') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'sales-register-'.$startDate.'-to-'.$endDate.'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function salesRegisterMatchingOrders(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        return Order::query()
            ->with(['items', 'customer', 'user'])
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->where('status', '!=', 'cancelled')
            ->when($request->input('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->input('customer_id'), fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->input('payment_method'), fn ($q, $m) => $q->where('payment_method', $m))
            ->when($request->input('delivery_method'), fn ($q, $m) => $q->where('delivery_method', $m))
            ->when($request->input('category_id'), fn ($q, $id) => $q->whereHas('items.product', fn ($p) => $p->where('category_id', $id)))
            ->when($request->input('brand'), function ($q, $brand) {
                $q->whereHas('items', function ($i) use ($brand) {
                    $i->where('product_name', 'like', $brand.' %')
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
            'filterOptions' => $this->salesRegisterFilterOptions(),
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
}

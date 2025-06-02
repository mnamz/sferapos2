<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        $query = Order::query()
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        // Calculate summary statistics
        $summary = [
            'total_sales' => $query->clone()->sum('orders.total'),
            'total_orders' => $query->clone()->count(),
            'average_order_value' => $query->clone()->avg('orders.total'),
            'total_tax' => $query->clone()->sum('orders.tax'),
            'total_profit' => $query->clone()->sum('orders.profit'),
        ];

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
            ->when($request->input('sort_column'), function($query, $column) use ($request) {
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
                    'created_at' => 'orders.created_at'
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
            }, function($query) {
                $query->latest('orders.created_at');
            })
            ->paginate(10)
            ->through(function ($order) {
                return [
                    'id' => $order->id,
                    'customer_name' => $order->customer ? $order->customer->name : 'Walk-in Customer',
                    'subtotal' => number_format($order->subtotal, 2),
                    'total' => number_format($order->total, 2),
                    'tax' => number_format($order->tax, 2),
                    'profit' => number_format($order->profit, 2),
                    'due' => number_format($order->due_amount, 2),
                    'status' => $order->status,
                    'payment_status' => $order->paid_amount >= $order->total ? 'paid' : 
                        ($order->paid_amount > 0 ? 'partial' : 'pending'),
                    'created_at' => Carbon::parse($order->created_at)->format('Y-m-d H:i:s'),
                    'cashier_name' => $order->user->name,
                ];
            });

        // Get profit details
        $profitDetails = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
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
            ->get();

        return Inertia::render('Reports/Index', [
            'summary' => $summary,
            'dailySales' => $dailySales,
            'orders' => $orders,
            'profitDetails' => $profitDetails,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        $orders = Order::query()
            ->with(['customer', 'user'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->latest()
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers with styling
        $headers = ['A1' => 'Order #', 'B1' => 'Customer', 'C1' => 'Total', 'D1' => 'Tax', 
                   'E1' => 'Profit', 'F1' => 'Due', 'G1' => 'Payment', 'H1' => 'Status',
                   'I1' => 'Payment Status', 'J1' => 'Cashier', 'K1' => 'Date'];
        
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        // Add data
        $row = 2;
        foreach ($orders as $order) {
            $sheet->setCellValue('A' . $row, $order->id);
            $sheet->setCellValue('B' . $row, $order->customer ? $order->customer->name : 'Walk-in Customer');
            $sheet->setCellValue('C' . $row, $order->total);
            $sheet->setCellValue('D' . $row, $order->tax);
            $sheet->setCellValue('E' . $row, $order->profit);
            $sheet->setCellValue('F' . $row, $order->due_amount);
            $sheet->setCellValue('G' . $row, $order->payment_method);
            $sheet->setCellValue('H' . $row, ucfirst($order->status));
            $sheet->setCellValue('I' . $row, $order->paid_amount >= $order->total ? 'Paid' : 
                ($order->paid_amount > 0 ? 'Partial' : 'Pending'));
            $sheet->setCellValue('J' . $row, $order->user->name);
            $sheet->setCellValue('K' . $row, $order->created_at->format('Y-m-d H:i:s'));
            
            // Format numbers
            $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'orders_report_' . $startDate . '_to_' . $endDate . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

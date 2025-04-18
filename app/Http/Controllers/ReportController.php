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
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        // Calculate summary statistics
        $summary = [
            'total_sales' => $query->clone()->sum('total'),
            'total_orders' => $query->clone()->count(),
            'average_order_value' => $query->clone()->avg('total'),
            'total_tax' => $query->clone()->sum('tax'),
        ];

        // Get daily sales data for the chart
        $dailySales = $query->clone()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(total) as total_sales'),
                DB::raw('SUM(tax) as total_tax')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Get orders for the table
        $orders = $query->clone()
            ->with(['customer', 'user'])
            ->latest()
            ->paginate(10)
            ->through(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer ? $order->customer->name : 'Walk-in Customer',
                    'total' => number_format($order->total, 2),
                    'tax' => number_format($order->tax, 2),
                    'status' => $order->status,
                    'payment_status' => $order->paid_amount >= $order->total ? 'paid' : 
                        ($order->paid_amount > 0 ? 'partial' : 'pending'),
                    'created_at' => Carbon::parse($order->created_at)->format('Y-m-d H:i:s'),
                    'cashier_name' => $order->user->name,
                ];
            });

        return Inertia::render('Reports/Index', [
            'summary' => $summary,
            'dailySales' => $dailySales,
            'orders' => $orders,
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
                   'E1' => 'Status', 'F1' => 'Payment Status', 'G1' => 'Cashier', 'H1' => 'Date'];
        
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        // Add data
        $row = 2;
        foreach ($orders as $order) {
            $sheet->setCellValue('A' . $row, $order->order_number);
            $sheet->setCellValue('B' . $row, $order->customer ? $order->customer->name : 'Walk-in Customer');
            $sheet->setCellValue('C' . $row, $order->total);
            $sheet->setCellValue('D' . $row, $order->tax);
            $sheet->setCellValue('E' . $row, ucfirst($order->status));
            $sheet->setCellValue('F' . $row, $order->paid_amount >= $order->total ? 'Paid' : 
                ($order->paid_amount > 0 ? 'Partial' : 'Pending'));
            $sheet->setCellValue('G' . $row, $order->user->name);
            $sheet->setCellValue('H' . $row, $order->created_at->format('Y-m-d H:i:s'));
            
            // Format numbers
            $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            
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

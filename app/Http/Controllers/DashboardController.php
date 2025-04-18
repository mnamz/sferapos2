<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Today's statistics
        $today = Carbon::today();
        $todayStats = [
            'sales' => Order::whereDate('created_at', $today)->sum('total'),
            'orders' => Order::whereDate('created_at', $today)->count(),
            'customers' => Customer::whereDate('created_at', $today)->count(),
        ];

        // This month's statistics
        $thisMonth = Carbon::now()->startOfMonth();
        $monthlyStats = [
            'sales' => Order::whereMonth('created_at', $today->month)->sum('total'),
            'orders' => Order::whereMonth('created_at', $today->month)->count(),
            'customers' => Customer::whereMonth('created_at', $today->month)->count(),
        ];

        // Recent orders
        $recentOrders = Order::with('customer')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer ? $order->customer->name : 'Walk-in Customer',
                    'total' => $order->total,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'created_at' => $order->created_at->diffForHumans(),
                ];
            });

        // Top selling products
        $topProducts = DB::table('order_items')
            ->select('product_name', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(total) as total_sales'))
            ->groupBy('product_name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        // Low stock alerts
        $lowStockProducts = Product::where('stock', '<=', function($query) {
            $query->select('low_stock_threshold')
                ->from('shop_settings')
                ->limit(1);
        })->get();

        // Sales chart data (last 7 days)
        $salesChart = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(total) as total_sales'),
            DB::raw('COUNT(*) as total_orders')
        )
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => Carbon::parse($item->date)->format('M d'),
                    'sales' => $item->total_sales,
                    'orders' => $item->total_orders,
                ];
            });

        return Inertia::render('Dashboard', [
            'todayStats' => $todayStats,
            'monthlyStats' => $monthlyStats,
            'recentOrders' => $recentOrders,
            'topProducts' => $topProducts,
            'lowStockProducts' => $lowStockProducts,
            'salesChart' => $salesChart,
        ]);
    }
} 
<?php

namespace App\Http\Controllers;

use App\Services\ConsolidationQueueService;
use App\Services\MyInvoisService;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class MyInvoisPushStatusController extends Controller
{
    public function index()
    {
        $myInvoisService = new MyInvoisService();
        $queueService = new ConsolidationQueueService();
        
        $pushResults = $myInvoisService->getPushResults();
        $queueStats = $queueService->getStats();
        $queuedOrders = $queueService->getQueuedOrders();
        
        // Load MyInvoisInvoice relationship
        $queuedOrders->load('myInvoisInvoice');
        
        return Inertia::render('MyInvois/PushStatus', [
            'pushResults' => $pushResults,
            'queueStats' => $queueStats,
            'queuedOrders' => $queuedOrders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer->name ?? 'Walk-in',
                    'total' => number_format($order->total, 2),
                    'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                    'has_myinvois_invoice' => $order->myInvoisInvoice ? true : false,
                ];
            }),
        ]);
    }

    public function push(Request $request)
    {
        try {
            $queueService = new ConsolidationQueueService();
            $myInvoisService = new MyInvoisService();
            
            // Get all orders in queue
            $orders = $queueService->getQueuedOrders();
            
            // Load MyInvoisInvoice relationship
            $orders->load('myInvoisInvoice');
            
            if ($orders->isEmpty()) {
                return back()->with('error', 'No orders in consolidation queue.');
            }
            
            // Filter out orders that already have MyInvois invoices
            $ordersToSubmit = $orders->filter(function ($order) {
                return !$order->myInvoisInvoice;
            });
            
            if ($ordersToSubmit->isEmpty()) {
                // Clear queue if all orders already have invoices
                $queueService->clearQueue();
                return back()->with('info', 'All orders in queue already have MyInvois invoices. Queue cleared.');
            }
            
            // Submit consolidated invoices
            $result = $myInvoisService->submitConsolidatedInvoices($ordersToSubmit);
            
            if ($result['success'] ?? false) {
                // Remove successfully submitted orders from queue
                foreach ($ordersToSubmit as $order) {
                    $order->refresh();
                    if ($order->myInvoisInvoice) {
                        $queueService->removeOrder($order->id);
                    }
                }
                
                $message = "Successfully pushed {$result['accepted_count']} invoice(s) to MyInvois.";
                if ($result['rejected_count'] > 0) {
                    $message .= " {$result['rejected_count']} invoice(s) were rejected.";
                }
                
                return back()->with('success', $message);
            } else {
                $errorMessage = "Failed to push consolidated invoices.";
                if (isset($result['error'])) {
                    $errorMessage .= " Error: " . $result['error'];
                }
                return back()->with('error', $errorMessage);
            }
        } catch (\Exception $e) {
            Log::error('Manual push failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('error', 'An error occurred while pushing invoices: ' . $e->getMessage());
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\ConsolidationQueueService;
use App\Services\MyInvoisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ConsolidateInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:consolidate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consolidate and push queued invoices to MyInvois';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $queueService = new ConsolidationQueueService();
        $myInvoisService = new MyInvoisService();
        
        $this->info('Starting invoice consolidation...');
        
        // Get all orders in queue
        $orders = $queueService->getQueuedOrders();
        
        // Load MyInvoisInvoice relationship
        $orders->load('myInvoisInvoice');
        
        if ($orders->isEmpty()) {
            $this->info('No orders in consolidation queue.');
            return Command::SUCCESS;
        }
        
        $this->info("Found {$orders->count()} order(s) in queue.");
        
        // Filter out orders that already have MyInvois invoices
        $ordersToSubmit = $orders->filter(function ($order) {
            return !$order->myInvoisInvoice;
        });
        
        if ($ordersToSubmit->isEmpty()) {
            $this->info('All orders in queue already have MyInvois invoices. Clearing queue...');
            $queueService->clearQueue();
            return Command::SUCCESS;
        }
        
        $this->info("Submitting {$ordersToSubmit->count()} order(s) to MyInvois...");
        
        // Submit consolidated invoices
        $result = $myInvoisService->submitConsolidatedInvoices($ordersToSubmit);
        
        if ($result['success'] ?? false) {
            $this->info("Successfully submitted consolidated invoices!");
            $this->info("Accepted: {$result['accepted_count']}, Rejected: {$result['rejected_count']}");
            $this->info("Submission UID: " . ($result['submission_uid'] ?? 'N/A'));
            
            // Remove successfully submitted orders from queue
            foreach ($ordersToSubmit as $order) {
                // Only remove if order now has MyInvois invoice
                $order->refresh();
                if ($order->myInvoisInvoice) {
                    $queueService->removeOrder($order->id);
                }
            }
            
            $this->info("Removed successfully submitted orders from queue.");
        } else {
            $this->error("Failed to submit consolidated invoices.");
            $this->error("Status Code: " . ($result['status_code'] ?? 'N/A'));
            if (isset($result['error'])) {
                $this->error("Error: " . $result['error']);
            }
        }
        
        // Show remaining orders in queue
        $remainingQueue = $queueService->getQueue();
        if (!empty($remainingQueue)) {
            $this->info("Remaining orders in queue: " . count($remainingQueue));
        } else {
            $this->info("Queue is now empty.");
        }
        
        return Command::SUCCESS;
    }
}

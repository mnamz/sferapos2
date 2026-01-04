<?php

namespace App\Console\Commands;

use App\Models\MyInvoisQueue;
use App\Services\MyInvoisService;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PushMyInvoisQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'myinvois:push-queue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push MyInvois invoices that are older than the configured delay hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $delayHours = config('services.myinvois.queue_delay_hours', 72);
        Log::info("Checking for MyInvois invoices older than {$delayHours} hours...");

        // Get invoices older than configured hours with pending status
        $cutoffTime = Carbon::now()->subHours($delayHours);
        
        $pendingInvoices = MyInvoisQueue::with('order')
            ->where('status', 'pending')
            ->where('created_at', '<=', $cutoffTime)
            ->whereHas('order', function($query) {
                $query->where('delivery_method', 'walk-in');
            })
            ->get();

        if ($pendingInvoices->isEmpty()) {
            $this->info('No invoices found that need to be pushed.');
            return 0;
        }

        Log::info("Found {$pendingInvoices->count()} invoice(s) to push.");

        $myInvoisService = app(MyInvoisService::class);
        $successCount = 0;
        $failCount = 0;

        foreach ($pendingInvoices as $queueItem) {
            $order = $queueItem->order;
            
            if (!$order) {
                $this->error("Order not found for queue item #{$queueItem->id}");
                $failCount++;
                continue;
            }

            // Double-check delivery method (in case it was changed after queuing)
            if ($order->delivery_method !== 'walk-in') {
                $this->warn("Skipping Order #{$order->id} - delivery method is '{$order->delivery_method}', not 'walk-in'");
                // Remove from queue since it's not a walk-in order
                $queueItem->delete();
                continue;
            }

            $this->line("Pushing invoice for Order #{$order->id}...");

            try {
                $result = $myInvoisService->submitInvoice($order);
                
                if ($result) {
                    Log::info("✓ Successfully pushed invoice for Order #{$order->id}");
                    $successCount++;
                } else {
                    Log::error("✗ Failed to push invoice for Order #{$order->id}");
                    $failCount++;
                }
            } catch (\Exception $e) {
                $this->error("✗ Exception pushing invoice for Order #{$order->id}: {$e->getMessage()}");
                $failCount++;
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  Success: {$successCount}");
        $this->info("  Failed: {$failCount}");
        $this->info("  Total: {$pendingInvoices->count()}");

        return $failCount > 0 ? 1 : 0;
    }
}

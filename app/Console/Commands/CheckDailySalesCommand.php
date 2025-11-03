<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\ShopSettings;
use App\Services\TmsReceiptService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckDailySalesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:check-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if there are sales for the day, if not push dummy invoice to TMS';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today('Asia/Kuala_Lumpur');
        
        // Check if there are any orders for today
        $orderCount = Order::whereDate('created_at', $today)->count();
        
        $this->info("Checking sales for {$today->format('Y-m-d')}");
        $this->info("Total orders found: {$orderCount}");
        
        if ($orderCount > 0) {
            $this->info('Sales exist for today. No action needed.');
            Log::info('Daily sales check: Sales exist', [
                'date' => $today->format('Y-m-d'),
                'order_count' => $orderCount,
            ]);
            return Command::SUCCESS;
        }
        
        // No sales found, send dummy receipt to TMS
        $this->warn('No sales found for today. Sending dummy receipt to TMS...');
        
        try {
            $tmsService = app(TmsReceiptService::class);
            
            if (!$tmsService->isEnabled()) {
                $this->error('TMS Receipt Service is not enabled (missing authorization token).');
                Log::warning('Daily sales check: TMS service not enabled', [
                    'date' => $today->format('Y-m-d'),
                ]);
                return Command::FAILURE;
            }
            
            // Build dummy receipt payload
            $dummyReceipt = $this->buildDummyReceiptPayload($today);
            
            // Send dummy receipt
            $success = $tmsService->sendReceipt($dummyReceipt);
            
            if ($success) {
                $this->info('✓ Dummy receipt sent successfully to TMS.');
                Log::info('Daily sales check: Dummy receipt sent', [
                    'date' => $today->format('Y-m-d'),
                    'payload' => $dummyReceipt,
                ]);
                return Command::SUCCESS;
            } else {
                $this->error('✗ Failed to send dummy receipt to TMS.');
                Log::error('Daily sales check: Failed to send dummy receipt', [
                    'date' => $today->format('Y-m-d'),
                ]);
                return Command::FAILURE;
            }
            
        } catch (\Throwable $e) {
            $this->error("Exception: {$e->getMessage()}");
            Log::error('Daily sales check: Exception occurred', [
                'date' => $today->format('Y-m-d'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
    
    /**
     * Build a dummy receipt payload with all zero values.
     */
    protected function buildDummyReceiptPayload(Carbon $date): array
    {
        $settings = ShopSettings::first();
        
        // Use current timestamp at 12:30 AM for the receipt
        $receiptDateTime = $date->copy()->setTime(0, 30, 0);
        
        return [
            'ReceiptNo'           => '0',
            'ReceiptDateAndTime2' => $receiptDateTime->format('Y-m-d H:i:s'),
            'SubTotal'            => 0.0,
            'DiscountPercent'     => 0.0,
            'DiscountAmount'      => 0.0,
            'GstPercent'          => $settings ? (float) $settings->tax_percentage : 0.0,
            'GstAmount'           => 0.0,
            'GrandTotal'          => 0.0,
            'IsVoid'              => false,
        ];
    }
}


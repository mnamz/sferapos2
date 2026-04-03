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
    protected $description = 'Consolidate and send all finalized daily receipts to TMS at end of day. Cancelled orders are excluded. Sends a dummy receipt if no sales exist.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today('Asia/Kuala_Lumpur');

        // Only non-deleted (non-cancelled) orders count as finalized sales
        $orders = Order::whereDate('created_at', $today)->get();
        $orderCount = $orders->count();

        $this->info("Consolidating sales for {$today->format('Y-m-d')}");
        $this->info("Finalized orders: {$orderCount}");

        try {
            $tmsService = app(TmsReceiptService::class);

            if (!$tmsService->isEnabled()) {
                $this->error('TMS Receipt Service is not enabled (missing authorization token).');
                Log::warning('Daily sales check: TMS service not enabled', [
                    'date' => $today->format('Y-m-d'),
                ]);
                return Command::FAILURE;
            }

            if ($orderCount === 0) {
                // No finalized sales (none created, or all cancelled) — send dummy
                $this->warn('No finalized sales for today. Sending dummy receipt to TMS...');

                $dummyReceipt = $this->buildDummyReceiptPayload($today);
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
            }

            // Batch-send all finalized receipts for today
            $this->info("Sending {$orderCount} receipt(s) to TMS...");

            $receipts = $orders->map(fn (Order $order) => $tmsService->buildReceiptPayload($order))->toArray();
            $success = $tmsService->sendReceipts($receipts);

            if ($success) {
                $this->info("✓ {$orderCount} receipt(s) sent successfully to TMS.");
                Log::info('Daily sales check: Receipts sent', [
                    'date' => $today->format('Y-m-d'),
                    'order_count' => $orderCount,
                ]);
                return Command::SUCCESS;
            } else {
                $this->error('✗ Failed to send receipts to TMS.');
                Log::error('Daily sales check: Failed to send receipts', [
                    'date' => $today->format('Y-m-d'),
                    'order_count' => $orderCount,
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


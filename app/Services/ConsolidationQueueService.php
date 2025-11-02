<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ConsolidationQueueService
{
    protected $queueFile;

    public function __construct()
    {
        $this->queueFile = storage_path('app/myinvois_consolidation_queue.json');
    }

    /**
     * Add order to consolidation queue
     */
    public function addOrder(Order $order): bool
    {
        $queue = $this->getQueue();
        
        // Check if order already exists in queue
        if (in_array($order->id, $queue)) {
            return false; // Already in queue
        }

        $queue[] = $order->id;
        return $this->saveQueue($queue);
    }

    /**
     * Remove order from consolidation queue
     */
    public function removeOrder(int $orderId): bool
    {
        $queue = $this->getQueue();
        $queue = array_filter($queue, fn($id) => $id !== $orderId);
        return $this->saveQueue(array_values($queue)); // Re-index array
    }

    /**
     * Check if order is in queue
     */
    public function isInQueue(int $orderId): bool
    {
        $queue = $this->getQueue();
        return in_array($orderId, $queue);
    }

    /**
     * Get all orders in queue
     */
    public function getQueue(): array
    {
        if (!file_exists($this->queueFile)) {
            return [];
        }

        $content = file_get_contents($this->queueFile);
        $data = json_decode($content, true);
        
        return is_array($data) ? $data : [];
    }

    /**
     * Get orders with details from queue
     */
    public function getQueuedOrders(): \Illuminate\Database\Eloquent\Collection
    {
        $queue = $this->getQueue();
        
        if (empty($queue)) {
            return collect();
        }

        return Order::whereIn('id', $queue)
            ->with(['customer', 'items', 'myInvoisInvoice'])
            ->get();
    }

    /**
     * Clear the queue
     */
    public function clearQueue(): bool
    {
        return $this->saveQueue([]);
    }

    /**
     * Save queue to file
     */
    protected function saveQueue(array $queue): bool
    {
        try {
            // Ensure directory exists
            $dir = dirname($this->queueFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $result = file_put_contents(
                $this->queueFile,
                json_encode($queue, JSON_PRETTY_PRINT)
            );

            return $result !== false;
        } catch (\Exception $e) {
            Log::error('Failed to save consolidation queue', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get queue statistics
     */
    public function getStats(): array
    {
        $queue = $this->getQueue();
        return [
            'count' => count($queue),
            'order_ids' => $queue
        ];
    }
}


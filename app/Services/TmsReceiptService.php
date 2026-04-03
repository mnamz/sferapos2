<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TmsReceiptService
{
    /**
     * The POS service endpoint.
     */
    protected string $endpoint;

    /**
     * Full Authorization header value (e.g. "Basic XXXXXABC").
     */
    protected ?string $authorization;

    /**
     * Indicates whether the request should be flagged as test.
     */
    protected bool $isTest;

    public function __construct()
    {
        $this->endpoint      = config('services.tms_receipts.endpoint');
        $this->authorization = config('services.tms_receipts.authorization');
        $this->isTest        = (bool) config('services.tms_receipts.is_test');
    }

    /**
     * Determine if the service is enabled (i.e. we have an Authorization token).
     */
    public function isEnabled(): bool
    {
        return filled($this->authorization);
    }

    /**
     * Build a receipt payload from an Order model.
     */
    public function buildReceiptPayload(Order $order, bool $void = false): array
    {
        $settings = ShopSettings::first();
        $discountPercent = $order->subtotal > 0 ? round(($order->discount / $order->subtotal) * 100, 2) : 0.0;

        return [
            'ReceiptNo'           => (string) $order->id,
            'ReceiptDateAndTime2' => $order->created_at->format('Y-m-d H:i:s'),
            'SubTotal'            => (float) $order->subtotal,
            'DiscountPercent'     => (float) $discountPercent,
            'DiscountAmount'      => (float) $order->discount,
            'GstPercent'          => $settings ? (float) $settings->tax_percentage : 0.0,
            'GstAmount'           => (float) $order->tax,
            'GrandTotal'          => (float) $order->total,
            'IsVoid'              => $void,
        ];
    }

    /**
     * Send a single receipt to the API.
     */
    public function sendReceipt(array $receipt): bool
    {
        return $this->sendReceipts([$receipt]);
    }

    /**
     * Send an array of receipts in one call (as per API contract).
     *
     * @param array<int, array<string, mixed>> $receipts
     * @return bool
     */
    public function sendReceipts(array $receipts): bool
    {
        if (! $this->isEnabled()) {
            Log::warning('TMS receipt service is disabled – missing Authorization token.');
            return false;
        }

        // Inject IsTest and ensure IsVoid is present in every receipt
        $payload = collect($receipts)->map(function (array $receipt) {
            return $receipt + [
                'IsTest' => $this->isTest,
            ];
        })->values()->all();

        // Log outgoing payload for traceability (will be JSON-encoded automatically).
        Log::info('TMS POS API Request', [
            'endpoint' => $this->endpoint,
            'payload'  => $payload,
        ]);

        try {
            $response = Http::withHeaders([
                    'Content-Type'  => 'text/json',
                    'Authorization' => $this->authorization,
                ])
                ->timeout(30)
                ->send('PUT', $this->endpoint, [
                    'body' => json_encode($payload),
                ]);

            Log::info('TMS POS API Response', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('TMS POS API Exception', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
}

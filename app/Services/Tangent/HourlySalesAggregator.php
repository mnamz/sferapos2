<?php

namespace App\Services\Tangent;

use App\Models\Order;
use App\Models\ShopSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class HourlySalesAggregator
{
    /** POS payment_method => internal tender bucket key. */
    private const TENDER_MAP = [
        'cash' => 'cash',
        'card' => 'visa',
        'e-wallet' => 'tng',
        'online_transfer' => 'others_amount',
        'bank_transfer' => 'others_amount',
    ];

    /**
     * Build the 24 hourly aggregate rows for one KL calendar day.
     *
     * @return array<int, array<string, mixed>> indexed 0..23
     */
    public function aggregate(CarbonInterface $day): array
    {
        $tz = config('services.tangent.timezone', 'Asia/Kuala_Lumpur');
        $start = CarbonImmutable::parse($day->format('Y-m-d'), $tz)->startOfDay();
        $endExclusive = $start->addDay();
        $gstRegistered = $this->resolveGstRegistered();

        $buckets = [];
        for ($h = 0; $h < 24; $h++) {
            $buckets[$h] = [
                'receipt_count' => 0,
                'gto' => 0.0, 'gst' => 0.0, 'discount' => 0.0,
                'cash' => 0.0, 'tng' => 0.0, 'visa' => 0.0,
                'mastercard' => 0.0, 'amex' => 0.0, 'voucher' => 0.0,
                'others_amount' => 0.0,
            ];
        }

        $orders = Order::query()
            ->whereNull('deleted_at')
            ->where('status', '<>', 'cancelled')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $endExclusive)
            ->get(['created_at', 'subtotal', 'discount', 'tax', 'payment_method']);

        foreach ($orders as $order) {
            $hour = (int) $order->created_at->copy()->setTimezone($tz)->format('G');
            $net = (float) $order->subtotal - (float) $order->discount; // ex-tax net
            $tender = self::TENDER_MAP[$order->payment_method] ?? 'others_amount';

            $buckets[$hour]['receipt_count'] += 1;
            $buckets[$hour]['gto'] += $net;
            $buckets[$hour]['gst'] += (float) $order->tax;
            $buckets[$hour]['discount'] += (float) $order->discount;
            $buckets[$hour][$tender] += $net;
        }

        $rows = [];
        foreach ($buckets as $h => $b) {
            $rows[$h] = [
                'hour' => $h,
                'receipt_count' => $b['receipt_count'],
                'gto' => round($b['gto'], 2),
                'gst' => round($b['gst'], 2),
                'discount' => round($b['discount'], 2),
                'service_charge' => 0.0,
                'no_of_pax' => 0,
                'cash' => round($b['cash'], 2),
                'tng' => round($b['tng'], 2),
                'visa' => round($b['visa'], 2),
                'mastercard' => round($b['mastercard'], 2),
                'amex' => round($b['amex'], 2),
                'voucher' => round($b['voucher'], 2),
                'others_amount' => round($b['others_amount'], 2),
                'gst_registered' => $gstRegistered,
            ];
        }

        return $rows;
    }

    private function resolveGstRegistered(): string
    {
        $configured = config('services.tangent.gst_registered');
        if ($configured !== null && $configured !== '') {
            return strtoupper((string) $configured) === 'Y' ? 'Y' : 'N';
        }

        $settings = ShopSettings::first();
        if (! $settings) {
            return 'N';
        }

        $registered = ($settings->enable_tax ?? true)
            && filled($settings->tax_number)
            && (float) $settings->tax_percentage > 0;

        return $registered ? 'Y' : 'N';
    }
}

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $order->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
        }
        .container {
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .shop-info {
            margin-bottom: 30px;
        }
        .order-info {
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
        }
        .totals {
            float: right;
            width: 300px;
        }
        .totals table {
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="position: relative; min-height: 90px; margin-bottom: 10px;">
            <div style="width: 60%;">
                <span style="font-weight: bold;">Bill From:</span><br>
                <span>{{ $settings->shop_name }}</span><br>
                @if($settings->company_number)
                    <span>{{ $settings->company_number }}</span><br>
                @endif
                <span>{{ $settings->shop_address }}</span><br>
                @if($settings->shop_phone)
                    <span style="font-weight: bold;">Tel:</span> <span>{{ $settings->shop_phone }}</span><br>
                @endif
                @if($settings->shop_email)
                    <span style="font-weight: bold;">Email:</span> <span>{{ $settings->shop_email }}</span><br>
                @endif
            </div>
            @if($settings->invoice_logo_path)
                <img src="{{ public_path('storage/'.$settings->invoice_logo_path) }}" alt="Logo" style="position: absolute; top: 0; right: 0; max-width: 320px; max-height: 80px;">
            @elseif($settings->logo_path)
                <img src="{{ public_path('storage/'.$settings->logo_path) }}" alt="Logo" style="position: absolute; top: 0; right: 0; max-width: 320px; max-height: 80px;">
            @endif
        </div>

        <hr style="margin: 20px 0;">

        <table style="width: 100%; margin-bottom: 10px; margin-top: 5px; border: none;">
            <tr>
                <td style="width: 50%; vertical-align: top; border: none;">
                    <span style="font-weight: bold;">Attn:</span><br>
                    @if($order->customer)
                        <span>{{ $order->customer->name }}</span><br>
                        @if($order->customer->phone)
                            <span style="font-weight: bold;">Tel:</span> <span>{{ $order->customer->phone }}</span><br>
                        @endif
                        @if($order->customer->email)
                            <span style="font-weight: bold;">Email:</span> <span>{{ $order->customer->email }}</span><br>
                        @endif
                        @if($order->customer->address)
                            <span style="font-weight: bold;">Address:</span> <span>{{ $order->customer->address }}</span><br>
                        @endif
                    @endif
                </td>
                <td style="width: 50%; vertical-align: top; border: none;">
                    <table style="width: 100%; border: none; font-size: 14px;">
                        @php
                            $host = parse_url(config('app.url'), PHP_URL_HOST);
                        @endphp
                        <tr>
                            <td style="border: none; font-weight: bold; padding: 1px 0;">Invoice</td>
                            @if (!($host == 'ops.sfera.my'))
                            <td style="border: none; text-align: right; padding: 1px 0;">{{ $order->formatted_invoice_number }}</td>
                            @else
                            <td style="border: none; text-align: right; padding: 1px 0;">{{ now()->format('dmY') . '-' . $order->id . '-INV' }}</td>
                            @endif
                        </tr>
                        <tr>
                            <td style="border: none; font-weight: bold; padding: 1px 0;">Invoice Date</td>
                            <td style="border: none; text-align: right; padding: 1px 0;">{{ $order->created_at->format($settings->date_format) }}</td>
                        </tr>
                        <tr>
                            @php
                                $host = parse_url(config('app.url'), PHP_URL_HOST);
                            @endphp
                            @if (!($host == 'ops.sfera.my'))
                            <td style="border: none; font-weight: bold; padding: 1px 0;">Salesman</td>
                            @else
                            <td style="border: none; font-weight: bold; padding: 1px 0;">Issued By</td>
                            @endif
                            <td style="border: none; text-align: right; padding: 1px 0;">{{ $order->user->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; font-weight: bold; padding: 1px 0;">Amount Due</td>
                            <td style="border: none; text-align: right; padding: 1px 0;">{{ $settings->currency }}{{ number_format($order->due_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; font-weight: bold; background: #ddd; padding: 1px 0;">Payment Status</td>
                            <td style="border: none; background: #ddd; text-align: right; padding: 1px 0;">{{ ucfirst($order->status) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- <div style="margin-bottom: 10px;">
            @if($order->customer && $order->customer->phone)
                <strong>Tel:</strong> {{ $order->customer->phone }}<br>
            @endif
            @if($order->customer && $order->customer->email)
                <strong>Email:</strong> {{ $order->customer->email }}<br>
            @endif
        </div> -->

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
            <thead>
                <tr style="background: #ddd;">
                    <th style="padding: 8px 4px; background: #ddd;border: none; font-weight: bold; text-align: center;">Item</th>
                    <th style="padding: 8px 4px; background: #ddd;border: none; font-weight: bold; text-align: center;">Quantity</th>
                    <th style="padding: 8px 4px; background: #ddd;border: none; font-weight: bold; text-align: center;">Unit Price (RM)</th>
                    <th style="padding: 8px 4px; background: #ddd;border: none; font-weight: bold; text-align: center;">Total (RM)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr style="border-bottom: 1px solid #ccc;">
                    <td style="padding: 6px 4px; border: none; text-align: left;">{{ $item->product_name }} {{ $item->remark ? '('.$item->remark.')' : '' }}</td>
                    <td style="padding: 6px 4px; border: none; text-align: center;">{{ $item->quantity }}</td>
                    <td style="padding: 6px 4px; border: none; text-align: center;">{{ number_format($item->price, 0) }}</td>
                    <td style="padding: 6px 4px; border: none; text-align: center;">{{ number_format($item->total, 0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <table style="width: 100%; margin-top: 5px; margin-bottom: 10px; border: none;">
            <tr>
                <td style="width: 50%; vertical-align: top; border: none;">
                    <span style="font-weight: bold;">Payment Method</span><br>
                    {{ ucwords(str_replace('_', ' ', $order->payment_method)) }}<br>
                    @php
                        $host = parse_url(config('app.url'), PHP_URL_HOST);
                    @endphp

                    @if (!($host == 'ops.sfera.my'))
                        <span style="font-weight: bold;">Delivery Method</span><br>
                        {{ $order->delivery_method ?? '-' }}<br>
                    @endif
                    @if (!empty($order->remarks))
                        <span style="font-weight: bold;">Remark</span><br>
                        {{ $order->remarks }}
                    @endif
                </td>
                <td style="width: 50%; vertical-align: top; border: none;">
                    <table style="width: 100%; border: none; font-size: 14px;">
                        <tr>
                            <td style="border: none; font-weight: bold; padding: 1px 0;">Subtotal</td>
                            <td style="border: none; text-align: right; padding: 1px 0;">{{ $settings->currency }} {{ number_format($order->subtotal, 0) }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; font-weight: bold; padding: 1px 0;">Tax</td>
                            <td style="border: none; text-align: right; padding: 1px 0;">{{ $settings->currency }} {{ number_format($order->tax, 0) }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; font-weight: bold; padding: 1px 0;">Discount</td>
                            <td style="border: none; text-align: right; padding: 1px 0;">{{ $settings->currency }} {{ number_format($order->discount ?? 0, 0) }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; font-weight: bold; background: #ddd; padding: 1px 0;">Total</td>
                            <td style="border: none; background: #ddd; text-align: right; font-weight: bold; padding: 1px 0;">{{ $settings->currency }} {{ number_format($order->total, 0) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div style="margin-top: 10px; margin-left: 10px;">
            <div style="float: left; width: 30%;">
                <strong>Bank No :</strong><br>
                @if($settings->footer_text)
                    {{ $settings->footer_text }}<br>
                @endif
            </div>
        </div>

        @if(isset($isQueued) && $isQueued && $qrCodeBase64)
        <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #ddd; text-align: center; clear: both;">
            <div style="margin-bottom: 15px;">
                <strong style="font-size: 14px;">Request Your E-Invoice</strong><br>
                <span style="font-size: 12px;">Please scan the QR code below to request your e-invoice:</span><br>
                <span style="font-size: 11px; color: #666; margin-top: 5px; display: inline-block;">Note: You can request your e-invoice within {{ $queueDelayHours ?? 72 }} hours of purchase.</span>
                @php
                    $invoiceDate = \Carbon\Carbon::parse($order->created_at);
                    $endOfMonth = $invoiceDate->copy()->endOfMonth();
                    $daysUntilEndOfMonth = $invoiceDate->diffInDays($endOfMonth, false);
                    $isApproachingEndOfMonth = $daysUntilEndOfMonth <= 3 && $daysUntilEndOfMonth >= 0;
                @endphp
                @if($isApproachingEndOfMonth)
                <br><span style="font-size: 11px; color: #d97706; margin-top: 5px; display: inline-block; font-weight: bold;">Important: Please request your e-invoice before the end of this month.</span>
                @endif
            </div>
            <div style="display: inline-block; padding: 15px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 5px;">
                <img src="{{ $qrCodeBase64 }}" alt="E-Invoice QR Code" style="width: 150px; height: 150px; display: block;">
            </div>
        </div>
        @endif
    </div>
</body>
</html> 